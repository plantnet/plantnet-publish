<?php

namespace Plantnet\DataBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Plantnet\DataBundle\Document\Plantunit;
use Plantnet\DataBundle\Document\Collection;
use Plantnet\DataBundle\Document\Module;
use Plantnet\DataBundle\Document\Property;
use Plantnet\DataBundle\Document\File;
use Plantnet\DataBundle\Document\Image;
use Plantnet\DataBundle\Document\Imageurl;
use Symfony\Component\HttpFoundation\Response;

use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\DoctrineODMMongoDBAdapter;

class DefaultController extends Controller
{
    
}
