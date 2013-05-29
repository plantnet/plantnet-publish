<?php

namespace Plantnet\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('PlantnetUserBundle:Default:index.html.twig');
    }
}
