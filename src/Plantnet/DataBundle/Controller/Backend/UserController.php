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
 * @Route("/admin/users")
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

    private function compareUsersName($a,$b){
        return strcmp($a->getUsernameCanonical(),$b->getUsernameCanonical());
    }

    private function sortUsersAlpha($users){
        usort($users,array($this,'compareUsersName'));
        return $users;
    }

    public function displayNewAction()
    {
        $nb=0;
        $userManager=$this->get('fos_user.user_manager');
        $users=$userManager->findUsers();
        foreach($users as $user){
            $roles=$user->getRoles();
            if(!in_array('ROLE_ADMIN',$roles)&&!in_array('ROLE_SUPER_ADMIN',$roles)){
                $nb++;
            }
        }
        return $this->render('PlantnetDataBundle:Backend\Users:new.html.twig',array(
            'nb'=>$nb
        ));
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

    /**
     * @Route("/{username}/edit", name="admin_users_edit")
     * @Template()
     */
    public function users_editAction($username)
    {
        $userManager=$this->get('fos_user.user_manager');
        $user=$userManager->findUserByUsername($username);
        if(!$user){
            throw $this->createNotFoundException('Unable to find User entity.');
        }
        $role='user';
        $roles=$user->getRoles();
        if(in_array('ROLE_ADMIN',$roles)){
            $role='admin';
        }
        if(in_array('ROLE_SUPER_ADMIN',$roles)){
            $role='superadmin';
        }
        $switchForm=$this->createSwitchForm($this->database_list(),$user->getDbName());
        $enableForm=$this->createEnableForm($username);
        $enableSuperAdminForm=false;
        if($user->getSuper()===true){
            $enableSuperAdminForm=$this->createEnableSuperAdminForm($username);
        }
        $deleteForm=$this->createDeleteForm($username);
        return $this->render('PlantnetDataBundle:Backend\Users:users_edit.html.twig',array(
            'user'=>$user,
            'role'=>$role,
            'switch_form'=>$switchForm->createView(),
            'delete_form'=>$deleteForm->createView(),
            'enable_form'=>$enableForm->createView(),
            'enable_super_form'=>($enableSuperAdminForm!=false)?$enableSuperAdminForm->createView():null,
            'current'=>'users'
        ));
    }

    private function createSwitchForm($dbs,$default)
    {
        $databases=array();
        foreach($dbs as $db){
            $databases[$this->get_prefix().$db]=$this->get_prefix().$db;
        }
        return $this->createFormBuilder(array('databases'=>$databases))
            ->add('dbs','choice',array(
                'choices'=>$databases,
                'required'=>false,
                'label'=>'Database',
                'data'=>$default
            ))
            ->getForm();
    }

    /**
     * Switch database.
     *
     * @Route("/{username}/switch", name="admin_users_switch")
     * @Method("post")
     */
    public function users_switchAction($username)
    {
        $userManager=$this->get('fos_user.user_manager');
        $user=$userManager->findUserByUsername($username);
        if(!$user){
            throw $this->createNotFoundException('Unable to find User entity.');
        }
        $form=$this->createSwitchForm($this->database_list(),$user->getDbName());
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            $form->bind($request);
            if($form->isValid()){
                $roles=$user->getRoles();
                if(in_array('ROLE_SUPER_ADMIN',$roles)&&$username==$this->container->get('security.context')->getToken()->getUser()->getUsernameCanonical()){
                    $user->setDbName($form->get('dbs')->getData());
                    $userManager->updateUser($user);
                }
            }
        }
        return $this->redirect($this->generateUrl('admin_users_edit',array('username'=>$username)));
    }

    private function createEnableSuperAdminForm($username)
    {
        return $this->createFormBuilder(array('username'=>$username))
            ->add('username','hidden')
            ->getForm();
    }

    /**
     * Enables a User (superadmin) entity.
     *
     * @Route("/{username}/enable_super_admin", name="admin_users_enable_super_admin")
     * @Method("post")
     */
    public function users_enable_super_adminAction($username)
    {
        $form=$this->createEnableForm($username);
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            $form->bind($request);
            if($form->isValid()){
                $userManager=$this->get('fos_user.user_manager');
                $user=$userManager->findUserByUsername($username);
                if(!$user||$user->getSuper()!=true){
                    throw $this->createNotFoundException('Unable to find User entity.');
                }
                $roles=$user->getRoles();
                if(!in_array('ROLE_ADMIN',$roles)&&!in_array('ROLE_SUPER_ADMIN',$roles)){
                    //check if database exists
                    $dbs_array=$this->database_list();
                    if(!in_array($user->getDbNameUq(),$dbs_array)){
                        //update user account
                        $user->setSuper(false);
                        $user->addRole('ROLE_SUPER_ADMIN');
                        $userManager->updateUser($user);
                    }
                    else{
                        echo 'Error...';
                        exit;
                    }
                }
            }
        }
        return $this->redirect($this->generateUrl('admin_users_edit',array('username'=>$username)));
    }

    private function createEnableForm($username)
    {
        return $this->createFormBuilder(array('username'=>$username))
            ->add('username','hidden')
            ->getForm();
    }

    /**
     * Enables a User entity.
     *
     * @Route("/{username}/enable", name="admin_users_enable")
     * @Method("post")
     */
    public function users_enableAction($username)
    {
        $form=$this->createEnableForm($username);
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            $form->bind($request);
            if($form->isValid()){
                $userManager=$this->get('fos_user.user_manager');
                $user=$userManager->findUserByUsername($username);
                if(!$user){
                    throw $this->createNotFoundException('Unable to find User entity.');
                }
                $roles=$user->getRoles();
                if(!in_array('ROLE_ADMIN',$roles)&&!in_array('ROLE_SUPER_ADMIN',$roles)){
                    //check if database exists
                    $dbs_array=$this->database_list();
                    if(!in_array($user->getDbNameUq(),$dbs_array)){
                        $dbName=$this->get_prefix().$user->getDbNameUq();
                        $connection=new \MongoClient();
                        $db=$connection->$dbName;
                        $db->listCollections();
                        //collections
                        $db->createCollection('Collection');
                        $db->createCollection('Config');
                        $db->createCollection('Image');
                        $db->createCollection('Location');
                        $db->createCollection('Other');
                        $db->createCollection('Module');
                        $db->createCollection('Plantunit');
                        $db->createCollection('Taxon');
                        $db->createCollection('Page');
                        //indexes
                        $db->Image->ensureIndex(array("title1"=>1,"title2"=>1));
                        $db->Location->ensureIndex(array("coordinates"=>"2d"));
                        // $db->Plantunit->ensureIndex(array("attributes"=>"text"));
                        $db->Taxon->ensureIndex(array("name"=>1));
                        //pages data
                        $db->Page->insert(array('name'=>'home','alias'=>'home','order'=>1));
                        $db->Page->insert(array('name'=>'mentions','alias'=>'mentions','order'=>2));
                        $db->Page->insert(array('name'=>'credits','alias'=>'credits','order'=>3));
                        $db->Page->insert(array('name'=>'contacts','alias'=>'contacts','order'=>4));
                        //init config
                        $db->Config->insert(array(
                            'defaultlanguage'=>$user->getDefaultlanguage(),
                            'islocked'=>false,
                            'originaldb'=>$dbName
                        ));
                        //update user account
                        $user->setDbName($dbName);
                        $user->addRole('ROLE_ADMIN');
                        $userManager->updateUser($user);
                    }
                    else{
                        echo 'Error...';
                        exit;
                    }
                }
            }
        }
        return $this->redirect($this->generateUrl('admin_users_edit',array('username'=>$username)));
    }

    private function createDeleteForm($username)
    {
        return $this->createFormBuilder(array('username'=>$username))
            ->add('username','hidden')
            ->getForm();
    }

    /**
     * Deletes a User entity.
     *
     * @Route("/{username}/delete", name="admin_users_delete")
     * @Method("post")
     */
    public function users_deleteAction($username)
    {
        $form=$this->createDeleteForm($username);
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            $form->bind($request);
            if($form->isValid()){
                $userManager=$this->get('fos_user.user_manager');
                $user=$userManager->findUserByUsername($username);
                if(!$user){
                    throw $this->createNotFoundException('Unable to find User entity.');
                }
                $roles=$user->getRoles();
                if(!in_array('ROLE_ADMIN',$roles)&&!in_array('ROLE_SUPER_ADMIN',$roles)){
                    $userManager->deleteUser($user);
                }
            }
        }
        return $this->redirect($this->generateUrl('admin_users_list'));
    }
}
