<?php

namespace Plantnet\DataBundle\Controller\Backend;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Component\Form\FormError;

/**
 * User controller.
 *
 * @Route("/admin/users", options={"i18n" = false})
 */
class UserController extends Controller
{
    private function getDataBase($user=null,$dm=null)
    {
        if($user){
            return $user->getDbName();
        }
        elseif($dm){
            return $dm->getConfiguration()->getDefaultDB();
        }
        return $this->container->getParameter('mdb_base');
    }

    private function compareUsersName($a,$b){
        return strcmp($a->getUsernameCanonical(),$b->getUsernameCanonical());
    }

    private function sortUsersAlpha($users){
        usort($users,array($this,'compareUsersName'));
        return $users;
    }

    /**
     * @Route("/", name="admin_users_list")
     * @Template()
     */
    public function users_listAction()
    {
        $userManager=$this->get('fos_user.user_manager');
        $users=$userManager->findUsers();
        $sorted_users=array();
        foreach($users as $user){
            $sorted_users[]=$user;
        }
        if(count($sorted_users)){
            $sorted_users=$this->sortUsersAlpha($sorted_users);
        }
        return $this->render('PlantnetDataBundle:Backend\Users:users_list.html.twig',array(
            'users'=>$sorted_users,
            'current'=>'users'
        ));
    }
}
