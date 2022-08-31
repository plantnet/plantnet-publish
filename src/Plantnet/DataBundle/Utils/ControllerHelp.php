<?php

namespace Plantnet\DataBundle\Utils;

class ControllerHelp
{
    static public function glossarize($dm,$collection,$string,$nl2br=false)
    {
        if($collection&&$collection->getGlossary()){
            $terms=array();
            $tmp=$dm->createQueryBuilder('PlantnetDataBundle:Definition')
                ->field('glossary')->references($collection->getGlossary())
                ->select('name')
                ->hydrate(false)
                ->getQuery()
                ->toArray();
            foreach($tmp as $term){
                $terms[]=$term['name'];
            }
            unset($tmp);
            return StringHelp::glossary_highlight($collection->getUrl(),$terms,$string,$nl2br);
        }
        return $string;
    }

    static public function database_list($prefix,$container)
    {
        //display databases without prefix
        $dbs_array=array();
        $connection=new \MongoClient($container->getParameter('mdb_connection_url'));
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

    static public function check_enable_project($project,$prefix,$context,$container)
    {
        $cleaned_prefix=substr($prefix,0,-1);
        $connection=new \MongoClient($container->getParameter('mdb_connection_url'));
        $db=$connection->$cleaned_prefix->Database->findOne(array(
            'link'=>$project
        ),array(
            'enable'=>1
        ));
        if($db){
            if(isset($db['enable'])&&$db['enable']===false){
                throw $context->createNotFoundException('Unable to find Project "'.$project.'".');
            }
        }
        $projects=self::database_list($prefix,$container);
        if(!in_array($project,$projects)){
            throw $context->createNotFoundException('Unable to find Project "'.$project.'".');
        }
    }

    public static function get_config($project,$dm,$context)
    {
        $config=$dm->createQueryBuilder('PlantnetDataBundle:Config')
            ->getQuery()
            ->getSingleResult();
        if(!$config){
            throw $context->createNotFoundException('Unable to find Config entity.');
        }
        $default=$config->getDefaultlanguage();
        if(!empty($default)){
            $context->getRequest()->setLocale($default);
        }
        return $config;
    }

    public static function make_translations($project,$route,$params,$context,$default_db)
    {
        $tab_links=array();
        $dm=$context->get('doctrine.odm.mongodb.document_manager');
        $dm->getConfiguration()->setDefaultDB($default_db);
        $database=$dm->createQueryBuilder('PlantnetDataBundle:Database')
            ->field('link')->equals($project)
            ->getQuery()
            ->getSingleResult();
        if(!$database){
            throw $context->createNotFoundException('Unable to find Database entity.');
        }
        $current=$database->getlanguage();
        $parent=$database->getParent();
        if($parent){
            $database=$parent;
        }
        $children=$database->getChildren();
        if(count($children)){
            $params['project']=$database->getLink();
            $tab_links[$database->getLanguage()]=array(
                'lang'=>$database->getLanguage(),
                'language'=>\Locale::getDisplayName($database->getLanguage(),$database->getLanguage()),
                'link'=>$context->get('router')->generate($route,$params,true),
                'active'=>($database->getLanguage()==$current)?1:0
            );
            $tab_sub_links=array();
            foreach($children as $child){
                if($child->getEnable()==true){
                    $params['project']=$child->getLink();
                    $tab_sub_links[$child->getLanguage()]=array(
                        'lang'=>$child->getLanguage(),
                        'language'=>\Locale::getDisplayName($child->getLanguage(),$child->getLanguage()),
                        'link'=>$context->get('router')->generate($route,$params,true),
                        'active'=>($child->getLanguage()==$current)?1:0
                    );
                }
            }
            if(count($tab_sub_links)){
                ksort($tab_sub_links);
                $tab_links=array_merge($tab_links,$tab_sub_links);
            }
            else{
                $tab_links=array();
            }
        }
        return $tab_links;
    }
}