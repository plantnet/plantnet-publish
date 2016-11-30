<?php
namespace Plantnet\DataBundle\Twig\Extension;

class TwigPregExtension extends \Twig_Extension
{
    public function getFilters()
    {
        return array(
            'var_dump'=>new \Twig_Filter_Function('var_dump'),
            'highlight'=>new \Twig_Filter_Method($this,'highlight'),
            'basename'=>new \Twig_Filter_Method($this,'basename'),
            'round'=>new \Twig_Filter_Method($this,'round'),
            'replace'=>new \Twig_Filter_Method($this,'replace'),
            'language'=>new \Twig_Filter_Method($this,'language'),
            'truncate'=>new \Twig_Filter_Method($this,'truncate'),
            'fileexists'=>new \Twig_Filter_Method($this,'fileexists'),
            'cleandesc'=>new \Twig_Filter_Method($this,'cleandesc'),
            'addLinks'=>new \Twig_Filter_Method($this,'addLinks')
        );
    }

    public function highlight($sentence,$expr) 
    {
        return preg_replace('/('.$expr.')/','<span style="color:red">\1</span>',$sentence);
    }

    public function basename($path)
    {
        return preg_replace('/^.+[\\\\\\/]/','',$path);
    }

    public function var_dump($var)
    {
        return var_dump($var);
    }

    public function round($var)
    {
        return round($var,2);
    }

    public function replace($var=null,$search=null,$replace=null)
    {
        if($var==null||$search==null||$replace==null){
            return $var;
        }
        return str_replace($search,$replace,$var);
    }

    public function language($var)
    {
        return \Locale::getDisplayName($var);
    }

    public function truncate($var,$length)
    {
        if(strlen($var)>$length){
            $var=substr($var,0,$length);
            return substr($var,0,strrpos($var,' ')).'...';
        }
        return $var;
    }

    public function fileexists($var)
    {
        if(file_exists($var)){
            return true;
        }
        return false;
    }

    public function cleandesc($var)
    {
        return str_replace('"','&bdquo;',$var);
    }

    public function getName()
    {
        return 'twig_preg_extension';
    }

    public function addLinks($string)
    {
        return preg_replace('/https?:\/\/[\w\-\.!~?=&+\*\'"(),\/]+/','<a href="$0" target="_blank">$0</a>',$string);
    }

}

