<?php

namespace Plantnet\DataBundle\Controller\Backend;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormError;

use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\DoctrineODMMongoDBAdapter;

use Plantnet\DataBundle\Document\Page,
    Plantnet\DataBundle\Form\Type\PageType;

use Plantnet\DataBundle\Document\Config,
    Plantnet\DataBundle\Form\Type\ConfigNameType;

//?
use Plantnet\DataBundle\Document\Plantunit;
use Plantnet\DataBundle\Document\Image;
use Plantnet\DataBundle\Document\Location;
use Plantnet\DataBundle\Document\Other;
use Plantnet\DataBundle\Document\Database;

/**
 * Admin controller.
 *
 * @Route("/admin")
 */
class AdminController extends Controller
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

    private function getDataBase($user=null,$dm=null)
    {
        if($user){
            $dm=$this->get('doctrine.odm.mongodb.document_manager');
            $dm->getConfiguration()->setDefaultDB($this->getDataBase());
            $database=$dm->createQueryBuilder('PlantnetDataBundle:Database')
                ->field('name')->equals($user->getDbName())
                ->getQuery()
                ->getSingleResult();
            if(!$database){
                throw $this->createNotFoundException('Unable to find Database entity.');
            }
            if($database->getEnable()===false){
                $this->get('session')->getFlashBag()->get('db_error',array());
                $this->get('session')->getFlashBag()->add(
                    'db_error',
                    'This database is no longer active, it has been disabled by the administrator or by the super administrator.'
                );
            }
            return $user->getDbName();
        }
        elseif($dm){
            return $dm->getConfiguration()->getDefaultDB();
        }
        return $this->container->getParameter('mdb_base');
    }

    public function displayTitleAction()
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        return $this->render('PlantnetDataBundle:Backend\Admin:title.html.twig', array(
            'title'=>str_replace($this->container->getParameter('mdb_base').'_','',$user->getDbName())
        ));
    }

    /**
     * @Route("/", name="admin_index")
     * @Template()
     */
    public function indexAction()
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $this->updateDbList($user);
        $config=null;
        $editForm=null;
        if($user->getDbName()){
            $dm=$this->get('doctrine.odm.mongodb.document_manager');
            $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
            $config=$dm->createQueryBuilder('PlantnetDataBundle:Config')
                ->getQuery()
                ->getSingleResult();
            if(!$config){
                throw $this->createNotFoundException('Unable to find Config entity.');
            }
            $editForm=$this->createForm(new ConfigNameType(),$config);
        }
        return $this->render('PlantnetDataBundle:Backend:index.html.twig',array(
            'config'=>$config,
            'edit_form'=>($editForm!=null)?$editForm->createView():null,
            'current'=>'index'
        ));
    }

    private function updateDbList($user)
    {
        $roles=$user->getRoles();
        if(in_array('ROLE_ADMIN',$roles)&&!in_array('ROLE_SUPER_ADMIN',$roles)){
            $dbs=$user->getDblist();
            if(empty($dbs)){
                $db=$user->getDbName();
                if($db){
                    $userManager=$this->get('fos_user.user_manager');
                    $dbList=array($db);
                    $user->setDblist($dbList);
                    $userManager->updateUser($user);
                }
            }
        }
    }

    /**
     * Edits an existing Config entity.
     *
     * @Route("/config/update/name", name="config_update_name")
     * @Method("post")
     * @Template()
     */
    public function config_update_nameAction()
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $config=$dm->createQueryBuilder('PlantnetDataBundle:Config')
            ->getQuery()
            ->getSingleResult();
        if(!$config){
            throw $this->createNotFoundException('Unable to find Config entity.');
        }
        $editForm=$this->createForm(new ConfigNameType(),$config);
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            $editForm->bind($request);
            if($editForm->isValid()){
                $dm->persist($config);
                $dm->flush();
                $name=$config->getOriginaldb();
                $displayedname=$config->getName();
                $language=$config->getDefaultlanguage();
                $dm->getConfiguration()->setDefaultDB($this->getDataBase());
                $database=$dm->getRepository('PlantnetDataBundle:Database')
                    ->findOneBy(array(
                        'name'=>$name,
                        'language'=>$language
                    ));
                if($database){
                    $database->setDisplayedname($displayedname);
                    $dm->persist($database);
                    $dm->flush();
                }
                $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
                $this->get('session')->getFlashBag()->add('msg_success','Project name updated');
                return $this->redirect($this->generateUrl('admin_index'));
            }
        }
        return $this->redirect($this->generateUrl('admin_index'));
    }

    /**
     * @Route("/collection/{collection}", name="admin_collection_view")
     * @Template()
     */
    public function collectionAction($collection)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array(
                'url'=>$collection
            ));
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        return $this->render('PlantnetDataBundle:Backend\Admin:collection.html.twig', array(
            'collection'=>$collection
        ));
    }

    /**
     * @Route("/collection/{collection}/module/{module}", name="admin_module_view")
     * @Template()
     */
    public function moduleAction($collection,$module)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array(
                'url'=>$collection
            ));
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array('url'=>$module,'collection.id'=>$collection->getId()));
        if(!$module||$module->getType()!='text'){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $display=array();
        $order=array();
        $field=$module->getProperties();
        foreach($field as $row){
            if($row->getMain()==true){
                $display[]=$row->getId();
            }
            if($row->getSortorder()){
                $order[$row->getSortorder()]=$row->getId();
            }
        }
        ksort($order);
        $queryBuilder=$dm->createQueryBuilder('PlantnetDataBundle:Plantunit')
            ->field('module')->references($module);
        if(count($order)){
            foreach($order as $num=>$prop){
                $queryBuilder->sort('attributes.'.$prop,'asc');
            }
        }
        $paginator=new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
        $paginator->setMaxPerPage(50);
        $paginator->setCurrentPage($this->get('request')->query->get('page',1));
        return $this->render('PlantnetDataBundle:Backend\Admin:datagrid.html.twig',array(
            'paginator'=>$paginator,
            'collection'=>$collection,
            'module'=>$module,
            'display'=>$display
        ));
    }

    /**
     * @Route("/collection/{collection}/module/{module}/module/{submodule}", name="admin_submodule_view")
     * @Template()
     */
    public function submoduleAction($collection,$module,$submodule)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $collection=$dm->getRepository('PlantnetDataBundle:Collection')
            ->findOneBy(array(
                'url'=>$collection
            ));
        if(!$collection){
            throw $this->createNotFoundException('Unable to find Collection entity.');
        }
        $module=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'url'=>$module,
                'collection.id'=>$collection->getId()
            ));
        if(!$module){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $mod=$dm->getRepository('PlantnetDataBundle:Module')
            ->findOneBy(array(
                'url'=>$submodule,
                'parent.id'=>$module->getId(),
                'collection.id'=>$collection->getId()
            ));
        if(!$mod||$mod->getType()=='text'){
            throw $this->createNotFoundException('Unable to find Module entity.');
        }
        $display=array();
        $field=$module->getProperties();
        foreach($field as $row){
            if($row->getMain()==true){
                $display[]=$row->getId();
            }
        }
        switch($mod->getType()){
            case 'image':
                $queryBuilder=$dm->createQueryBuilder('PlantnetDataBundle:Image')
                    ->field('module')->references($mod)
                    ->sort('title1','asc')
                    ->sort('title2','asc')
                    ->hydrate(false);
                $paginator=new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
                $paginator->setMaxPerPage(20);
                $paginator->setCurrentPage($this->get('request')->query->get('page',1));
                return $this->render('PlantnetDataBundle:Backend\Admin:gallery.html.twig',array(
                    'paginator'=>$paginator,
                    'collection'=>$collection,
                    'module'=>$mod,
                    'module_parent'=>$module,
                ));
                break;
            case 'locality':
                $db=$this->getDataBase($user);
                $m=new \MongoClient();
                // $plantunits=array();
                // $c_plantunits=$m->$db->Plantunit->find(
                //     array('module.$id'=>new \MongoId($module->getId())),
                //     array('_id'=>1,'title1'=>1,'title2'=>1)
                // );
                // foreach($c_plantunits as $id=>$p)
                // {
                //     $plant=array();
                //     $plant['title1']='';
                //     $plant['title2']='';
                //     if(isset($p['title1']))
                //     {
                //         $plant['title1']=$p['title1'];
                //     }
                //     if(isset($p['title2']))
                //     {
                //         $plant['title2']=$p['title2'];
                //     }
                //     $plantunits[$id]=$plant;
                // }
                // unset($c_plantunits);
                $locations=array();
                $c_locations=$m->$db->Location->find(
                    array(
                        'module.$id'=>new \MongoId($mod->getId())
                    ),
                    array(
                        '_id'=>1,
                        'latitude'=>1,
                        'longitude'=>1,
                        'plantunit'=>1,
                        'title1'=>1,
                        'title2'=>1
                    )
                );
                foreach($c_locations as $id=>$l){
                    $loc=array();
                    $loc['id']=$id;
                    $loc['latitude']=$l['latitude'];
                    $loc['longitude']=$l['longitude'];
                    $loc['title1']=$l['title1'];
                    $loc['title2']=$l['title2'];
                    // if(array_key_exists($l['plantunit']['$id']->{'$id'},$plantunits))
                    // {
                    //     if(isset($plantunits[$l['plantunit']['$id']->{'$id'}]['title1']))
                    //     {
                    //         $loc['title1']=$plantunits[$l['plantunit']['$id']->{'$id'}]['title1'];
                    //     }
                    //     if(isset($plantunits[$l['plantunit']['$id']->{'$id'}]['title2']))
                    //     {
                    //         $loc['title2']=$plantunits[$l['plantunit']['$id']->{'$id'}]['title2'];
                    //     }
                    // }
                    $locations[]=$loc;
                }
                // unset($plantunits);
                unset($c_locations);
                unset($m);
                $dir=$this->get('kernel')->getBundle('PlantnetDataBundle')->getPath().'/Resources/config/';
                $layers=new \SimpleXMLElement($dir.'layers.xml',0,true);
                return $this->render('PlantnetDataBundle:Backend\Admin:map.html.twig',array(
                    'collection'=>$collection,
                    'module'=>$mod,
                    'module_parent'=>$module,
                    'locations'=>$locations,
                    'layers'=>$layers
                ));
                break;
            case 'other':
                $display=array();
                $order=array();
                $field=$mod->getProperties();
                foreach($field as $row){
                    if($row->getDetails()==true){
                        $display[]=$row->getId();
                    }
                    if($row->getSortorder()){
                        $order[$row->getSortorder()]=$row->getId();
                    }
                }
                ksort($order);
                $queryBuilder=$dm->createQueryBuilder('PlantnetDataBundle:Other')
                    ->field('module')->references($mod)
                    ->hydrate(false);
                if(count($order)){
                    foreach($order as $num=>$prop){
                        $queryBuilder->sort('property.'.$prop,'asc');
                    }
                }
                $paginator=new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder));
                $paginator->setMaxPerPage(50);
                $paginator->setCurrentPage($this->get('request')->query->get('page',1));
                return $this->render('PlantnetDataBundle:Backend\Admin:other.html.twig',array(
                    'paginator'=>$paginator,
                    'collection'=>$collection,
                    'module'=>$mod,
                    'module_parent'=>$module,
                    'display'=>$display,
                ));
                break;
        }
    }

    public function page_listAction()
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $pages=$dm->createQueryBuilder('PlantnetDataBundle:Page')
            ->sort('order','ASC')
            ->getQuery()
            ->execute();
        return $this->render('PlantnetDataBundle:Backend\Admin:page_list.html.twig',array(
            'pages'=>$pages,
            'current'=>'administration'
        ));
    }

    /**
     * Displays a form to edit a page.
     *
     * @Route("/page/{alias}/edit", name="page_edit")
     * @Template()
     */
    public function page_editAction($alias)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $page=$dm->getRepository('PlantnetDataBundle:Page')
            ->findOneBy(array(
                'alias'=>$alias
            ));
        if(!$page){
            throw $this->createNotFoundException('Unable to find Page entity.');
        }
        $editForm=$this->createForm(new PageType(),$page);
        return $this->render('PlantnetDataBundle:Backend\Admin:page_edit.html.twig',array(
            'page'=>$page,
            'edit_form'=>$editForm->createView(),
            'current'=>'pages'
        ));
    }

    /**
     * Edits an existing Page entity.
     *
     * @Route("/page/{alias}/update", name="page_update")
     * @Method("post")
     * @Template()
     */
    public function page_updateAction($alias)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase($user,$dm));
        $page=$dm->getRepository('PlantnetDataBundle:Page')
            ->findOneBy(array(
                'alias'=>$alias
            ));
        if(!$page){
            throw $this->createNotFoundException('Unable to find Page entity.');
        }
        $editForm=$this->createForm(new PageType(),$page);
        $request=$this->getRequest();
        if('POST'===$request->getMethod()){
            $editForm->bind($request);
            if($editForm->isValid()){
                $dm->persist($page);
                $dm->flush();
                $this->get('session')->getFlashBag()->add('msg_success','Page updated');
                return $this->redirect($this->generateUrl('page_edit',array('alias'=>$alias)));
            }
        }
        return $this->render('PlantnetDataBundle:Backend\Admin:page_edit.html.twig',array(
            'page'=>$page,
            'edit_form'=>$editForm->createView(),
            'current'=>'pages'
        ));
    }

    private function createDatabaseNewForm()
    {
        //not null, ctype_lower (only letters), 3-50 chars
        return $this->createFormBuilder()
            ->add('dbname','text',array(
                'required'=>true,
                'label'=>'Database name (only letters):'
            ))
            ->add('defaultlanguage','language',array(
                'label'=>'Default language:',
                'required'=>true
            ))
            ->getForm();
    }

    /**
     * Displays a form to create a new Database.
     *
     * @Route("/database/new", name="database_new")
     * @Template()
     */
    public function database_newAction()
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $form=$this->createDatabaseNewForm();
        return $this->render('PlantnetDataBundle:Backend\Admin:database_new.html.twig',array(
            'form'=>$form->createView()
        ));
    }

    /**
     * Creates a new Database.
     *
     * @Route("/database/create", name="database_create")
     * @Method("post")
     * @Template()
     */
    public function collection_createAction(Request $request)
    {
        $user=$this->container->get('security.context')->getToken()->getUser();
        $form=$this->createDatabaseNewForm();
        $roles=$user->getRoles();
        if($request->isMethod('POST')){
            $form->bind($request);
            if(in_array('ROLE_ADMIN',$roles)&&!in_array('ROLE_SUPER_ADMIN',$roles)){
                $dbName=$form->get('dbname');
                $language=$form->get('defaultlanguage');
                if(!is_null($dbName->getData())){
                    if(!ctype_lower($dbName->getData())){
                        $dbName->addError(new FormError("This field is not valid (only letters)"));
                    }
                    if(strlen($dbName->getData())<3||strlen($dbName->getData())>50){
                        $dbName->addError(new FormError("This field must contain 3 to 50 letters"));
                    }
                }
                else{
                    $dbName->addError(new FormError("This field must not be empty"));
                }
                $dbs=$this->database_list();
                if(in_array($dbName->getData(),$dbs)){
                   $dbName->addError(new FormError('This value is already used.'));
                }
                if($form->isValid()){
                    $new_db=$this->get_prefix().$dbName->getData();
                    //add Database entity
                    $dm=$this->get('doctrine.odm.mongodb.document_manager');
                    $database=new Database();
                    $database->setName($new_db);
                    $database->setDisplayedname(ucfirst($dbName->getData()));
                    $database->setLink($dbName->getData());
                    $database->setLanguage($language->getData());
                    $dm->persist($database);
                    $dm->flush();
                    //create new database
                    $connection=new \MongoClient();
                    $db=$connection->$new_db;
                    $db->listCollections();
                    //collections
                    $db->createCollection('Collection');
                    $db->createCollection('Config');
                    $db->createCollection('Definition');
                    $db->createCollection('Glossary');
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
                    $db->Page->insert(array('name'=>'Home','alias'=>'home','order'=>1));
                    $db->Page->insert(array('name'=>'Mentions','alias'=>'mentions','order'=>2));
                    $db->Page->insert(array('name'=>'Credits','alias'=>'credits','order'=>3));
                    $db->Page->insert(array('name'=>'Contacts','alias'=>'contacts','order'=>4));
                    //init config
                    $db->Config->insert(array(
                        'defaultlanguage'=>$language->getData(),
                        'islocked'=>false,
                        'originaldb'=>$new_db,
                        'name'=>ucfirst($dbName->getData())
                    ));
                    //update user account
                    $userManager=$this->get('fos_user.user_manager');
                    $db_list=$user->getDblist();
                    $db_list[]=$new_db;
                    $user->setDblist($db_list);
                    $userManager->updateUser($user);
                    $this->get('session')->getFlashBag()->add('msg_success','Database created');
                    return $this->redirect($this->generateUrl('admin_index'));
                }
            }
        }
        return $this->render('PlantnetDataBundle:Backend\Admin:database_new.html.twig',array(
            'form'=>$form->createView()
        ));
    }

    /**
     * @Route("/database_switch/{database}", name="database_switch")
     * @Template()
     */
    public function database_switchAction($database)
    {
        $userManager=$this->get('fos_user.user_manager');
        $user=$this->container->get('security.context')->getToken()->getUser();
        $dm=$this->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($this->getDataBase());
        $db=$dm->getRepository('PlantnetDataBundle:Database')
            ->findOneBy(array(
                'name'=>$database
            ));
        if(!$db){
            throw $this->createNotFoundException('Unable to find Database entity.');
        }
        $db_list=$user->getDblist();
        if(!in_array($database,$db_list)){
            throw $this->createNotFoundException('Unable to switch.');
        }
        $user->setDbName($database);
        $userManager->updateUser($user);
        $this->get('session')->getFlashBag()->add('msg_success','Switched to '.str_replace($this->get_prefix(),'',$database).' project');
        return $this->redirect($this->generateUrl('admin_index'));
    }
}
