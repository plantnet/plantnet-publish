<?php

namespace Plantnet\UserBundle\Controller;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use FOS\UserBundle\Controller\RegistrationController as BaseController;

class RegistrationController extends BaseController
{
    /**
     * Tell the user his account is now confirmed
     */
    public function confirmedAction()
    {
        $response=parent::confirmedAction();
        // new functionality
        $userManager=$this->container->get('fos_user.user_manager');
        $users=$userManager->findUsers();
        foreach($users as $u){
            $roles=$u->getRoles();
            if(in_array('ROLE_SUPER_ADMIN',$roles)){
                $message=\Swift_Message::newInstance()
                    ->setSubject('Publish : new user')
                    ->setFrom($this->container->getParameter('from_email_adress'))
                    ->setTo($u->getEmail())
                    ->setBody($this->container->get('templating')->render(
                        'PlantnetUserBundle:Registration:email_new.txt.twig'
                    ))
                ;
                $this->container->get('mailer')->send($message);
            }
        }
        // /new functionality
        return $response;
    }
}
