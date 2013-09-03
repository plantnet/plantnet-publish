<?php

namespace Plantnet\UserBundle\Controller;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use FOS\UserBundle\Controller\RegistrationController as BaseController;

use Symfony\Component\HttpFoundation\Request;
use FOS\UserBundle\FOSUserEvents;
use FOS\UserBundle\Event\UserEvent;
use FOS\UserBundle\Event\FormEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use FOS\UserBundle\Event\FilterUserResponseEvent;
use Symfony\Component\Form\FormError;

class RegistrationController extends BaseController
{
    private function database_list()
    {
        //display databases without prefix
        $prefix=$this->get_prefix();
        $dbs_array=array();
        $connection=new \MongoClient();
        $dbs=$connection->admin->command(array(
            'listDatabases'=>1
        ));
        foreach($dbs['databases'] as $db){
            $db_name=$db['name'];
            if(substr_count($db_name,$prefix)){
                $dbs_array[]=str_replace($prefix,'',$db_name);
            }
        }
        return $dbs_array;
    }

    private function get_prefix()
    {
        return $this->container->getParameter('mdb_base').'_';
    }

    public function registerAction(Request $request)
    {
        /** @var $formFactory \FOS\UserBundle\Form\Factory\FactoryInterface */
        $formFactory = $this->container->get('fos_user.registration.form.factory');
        /** @var $userManager \FOS\UserBundle\Model\UserManagerInterface */
        $userManager = $this->container->get('fos_user.user_manager');
        /** @var $dispatcher \Symfony\Component\EventDispatcher\EventDispatcherInterface */
        $dispatcher = $this->container->get('event_dispatcher');

        $user = $userManager->createUser();
        $user->setEnabled(true);

        $dispatcher->dispatch(FOSUserEvents::REGISTRATION_INITIALIZE, new UserEvent($user, $request));

        $form = $formFactory->createForm();
        $form->setData($user);

        if ('POST' === $request->getMethod()) {
            $form->bind($request);

            // new functionality
            $chk_db_name=$user->getDbNameUq();
            if($chk_db_name){
                $dbs=$this->database_list();
                if(in_array($chk_db_name,$dbs)){
                   $form->get('dbNameUq')->addError(new FormError('This value is already used.'));
                }
            }
            // /new functionality

            if ($form->isValid()) {
                $event = new FormEvent($form, $request);
                $dispatcher->dispatch(FOSUserEvents::REGISTRATION_SUCCESS, $event);

                $userManager->updateUser($user);

                if (null === $response = $event->getResponse()) {
                    $url = $this->container->get('router')->generate('fos_user_registration_confirmed');
                    $response = new RedirectResponse($url);
                }

                $dispatcher->dispatch(FOSUserEvents::REGISTRATION_COMPLETED, new FilterUserResponseEvent($user, $request, $response));

                return $response;
            }
        }

        return $this->container->get('templating')->renderResponse('FOSUserBundle:Registration:register.html.'.$this->getEngine(), array(
            'form' => $form->createView(),
        ));
    }

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
