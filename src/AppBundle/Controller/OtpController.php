<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Otp;
use AppBundle\Form\EmailType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class OtpController extends Controller
{
    /**
     * @Route("/otp/create", name="otp_create")
     */
    public function createAction(Request $request)
    {
        $otp = new Otp();
        $form = $this->createForm(new EmailType(), $otp);
        $form->handleRequest($request);

        //$totp = new \OTPHP\TOTP("JBSWY3DPEHPK3PXP");

        if($form->isSubmitted() && $form->isValid()) {
            return new Response('Email sent');
        }

        // replace this example code with whatever you need
        return $this->render('otp/create.html.twig', array(
            'form' => $form->createView()
        ));
    }
}
