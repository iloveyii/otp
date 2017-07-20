<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Member;
use AppBundle\Form\MemberType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Session\Session;

use OTPHP\HOTP;

class MemberController extends Controller
{
    const VALID = 1;
    const INVALID = 2;
    const EXPIRED = 3;

    /**
     * @Route("/member/register", name="register")
     * @param Request $request
     * @return Response
     */
    public function registerAction(Request $request)
    {
        $member = new Member();
        $form = $this->createForm(new MemberType(), $member);
        $form->handleRequest($request);

        $hOtp = new HOTP();
        $otp = $hOtp->at(0);

        if($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $member->setRetry(0);
            $member->setCode($otp);
            $em->persist($member);
            $em->flush();

            $this->get('session')->set('email', $member->getEmail());
            $this->setFlash(
                'info',
                'A code has been sent to your email! Please check your email and enter the code here.'
            );

            $this->sendEmail($member->getEmail(), $otp);

            return $this->redirectToRoute('verify');
        }

        return $this->render('member/register.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/verify/code", name="verify")
     * @param Request $request
     * @return Response
     */
    public function verifyAction(Request $request)
    {
        $member = new Member();

        $form = $this->createFormBuilder($member)
                ->add('code', TextType::class)
                ->add('save', SubmitType::class, array('label' => 'Verify'))
                ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted()) {

            $data = $form->getData();
            $code = $data->getCode();
            $retryAttempt = self::INVALID; // let

            // if code is entered then check
            if( ! empty($code)) {
                $repository = $this->getDoctrine()->getRepository(Member::class);
                // sessions hack
                if(empty($this->get('session')->get('email'))) {
                    $member = $repository->findOneBy(array('code' => $code), array('id'=>'DESC'));
                } else {
                    $member = $repository->findOneBy(array('code' => $code, 'email' => $this->get('session')->get('email')));
                }

                // if code and email exist in db then either valid or expired
                if($member) {
                    $retryAttempt = $member->getRetry() >= Member::RETRY_THRESHOLD ? self::EXPIRED : self::VALID;
                    $em = $this->getDoctrine()->getManager();
                    $member->incrementRetry();
                    $em->persist($member);
                    $em->flush();
                }
            }

            switch ($retryAttempt) {
                case self::VALID :
                    $this->setFlash(
                        'info',
                        'Your code is valid !'
                    );
                    break;

                case self::INVALID :
                    $this->setFlash(
                        'error',
                        'Your code is not valid !'
                    );
                    break;

                case self::EXPIRED :
                    $this->setFlash(
                        'error',
                        'Your code is expired !'
                    );
                    break;
            }
        }

        return $this->render('member/verifycode.html.twig', [
            'form' => $form->createView(),
            'loginLink' => $this->generateUrl('login')
        ]);
    }

    /**
     * @Route("/member/list", name="list")
     * @return mixed
     */
    public function listAction()
    {
        $repository = $this->getDoctrine()->getRepository(Member::class);
        $members = $repository->findBy([], ['id' => 'DESC']);

        return $this->render('member/list.html.twig', [
            'members' => $members
        ]);
    }

    /**
     * @Route("/login", name="login")
     * @param Request $request
     * @return mixed
     */
    public function loginAction(Request $request)
    {
        $authenticationUtils = $this->get('security.authentication_utils');
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('member/login.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    /**
     * @Route("/logout", name="logout")
     * @return mixed
     */
    public function logoutAction()
    {
        $session = new Session();
        $session->invalidate();

        return $this->redirectToRoute('register');
    }

    /**
     * @param string $type
     * @param string $message
     */
    private function  setFlash($type, $message)
    {
        $session = new Session();
        $flashBag = $session->getFlashBag();
        $flashBag->clear();
        $flashBag->add(
            $type,
            $message
        );
    }

    /**
     * @param string $email
     * @param string $code
     */
    private function sendEmail($email, $code)
    {
        $message = (new \Swift_Message('One time password'))
            ->setFrom('ali.symfony@gmail.com')
            ->setTo($email)
            ->setBody(
                $this->renderView(
                    'member/email.html.twig',
                    array('code' => $code)
                ),
                'text/html'
            )
            ->addPart(
                $this->renderView(
                    'member/email.txt.twig',
                    array('code' => $code)
                ),
                'text/plain'
            )
        ;

        $this->get('mailer')
            ->send($message);
    }

}