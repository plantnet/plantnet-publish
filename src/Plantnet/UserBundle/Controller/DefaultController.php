<?php

/**
 * This file is part of the Identify package.
 *
 * (c) Julien Barbe <julien.barbe@cirad.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace Plantnet\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('PlantnetUserBundle:Default:index.html.twig');
    }
}
