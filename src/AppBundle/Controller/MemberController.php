<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Member;
use AppBundle\Form\MemberType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
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
    public function register(Request $request)
    {
        $member = new Member();
        $form = $this->createForm(new MemberType(), $member);
        $form->handleRequest($request);

        $hotp = new HOTP();
        $otp = $hotp->at(0);


        if($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $member->setRetry(0);
            $member->setCode($otp);
            $em->persist($member);
            $em->flush();

            $this->get('session')->set('email', $member->getEmail());
            $this->setFlash(
                'info',
                'A code has been sent to your email ! Please check your email and enter the code here.'
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
                ->add('code', NumberType::class)
                ->add('save', SubmitType::class, array('label' => 'Verify'))
                ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted()) {
            $data = $form->getData();
            $code = $data->getCode();
            $retry = self::INVALID;

            if( ! empty($code)) {
                $repository = $this->getDoctrine()->getRepository(Member::class);
                $member = $repository->findOneBy(array('code'=>$code, 'email'=>$this->get('session')->get('email')));

                if($member) {
                    $retry = $member->getRetry() >= Member::RETRY_THRESHOLD ? self::EXPIRED : self::VALID;
                    $em = $this->getDoctrine()->getManager();
                    $member->incrementRetry();
                    $em->persist($member);
                    $em->flush();
                }
            }

            switch ($retry) {
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

    private function sendEmail($email, $code)
    {
        $message = (new \Swift_Message('Hello Email'))
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

    /**
     * @Route("/member/login", name="login")
     * @param Request $request
     * @return mixed
     */
    public function loginAction(Request $request)
    {
        return $this->render('member/login.html.twig', [

        ]);
    }

    /**
     * @Route("/member/list", name="list")
     * @return mixed
     */
    public function listAction()
    {
        $repository = $this->getDoctrine()->getRepository(Member::class);
        $members = $repository->findAll();

        return $this->render('member/list.html.twig', [
            'members' => $members
        ]);
    }


}