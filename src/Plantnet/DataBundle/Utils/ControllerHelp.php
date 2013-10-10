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
}