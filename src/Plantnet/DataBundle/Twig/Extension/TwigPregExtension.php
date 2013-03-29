<?php
namespace Plantnet\DataBundle\Twig\Extension;

class TwigPregExtension extends \Twig_Extension {

    public function getFilters() {
        return array(
            'var_dump' => new \Twig_Filter_Function('var_dump'),
            'highlight' => new \Twig_Filter_Method($this, 'highlight'),
            'basename' => new \Twig_Filter_Method($this, 'basename'),
            'round' => new \Twig_Filter_Method($this, 'round'),
            
        );
    }

    public function highlight($sentence, $expr) {
        return preg_replace('/(' . $expr . ')/', '<span style="color:red">\1</span>', $sentence);
    }

    public function basename($path) {
        return preg_replace( '/^.+[\\\\\\/]/', '', $path );
    }


    public function var_dump($var) {
        return var_dump($var);
    }

    public function round($var) {
        return round($var, 2);
    }


    public function getName()
    {
        return 'twig_preg_extension';
    }

}

