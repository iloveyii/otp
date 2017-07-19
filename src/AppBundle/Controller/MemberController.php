<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Member;
use AppBundle\Form\MemberType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use OTPHP\HOTP;

class MemberController extends Controller
{
    /**
     * @Route("/member/login", name="login")
     * @param Request $request
     * @return Response
     */
    public function loginAction(Request $request)
    {
        $member = new Member();
        $form = $this->createForm(new MemberType(), $member);
        $form->handleRequest($request);

        $hotp = new HOTP();
        $otp = $hotp->at(0);

        if($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            $data = $form->getData();
            $member->setEmail($data->getEmail());
            $member->setRetry(0);
            $member->setCode($otp);
            $em->persist($member);
            $em->flush();

            return new Response('Email sent');
        }

        return $this->render('member/login.html.twig', [
            'form' => $form->createView()
        ]);
    }
}