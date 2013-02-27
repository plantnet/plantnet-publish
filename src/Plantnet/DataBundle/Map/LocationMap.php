<?php

namespace Plantnet\DataBundle\Map;

use Vich\GeographicalBundle\Map\Map;
use Vich\GeographicalBundle\Map\Marker\MapMarker;

use Doctrine\ODM\MongoDB\DocumentManager;

/**
 * LocationMap.
 */
class LocationMap extends Map
{
    /**
     * Constructs a new instance of LocationMap.
     */
    public function __construct(DocumentManager $dm)
    {
        parent::__construct();
        //do something with $dm ...
        $this->setAutoZoom(true);
        $this->setContainerId('location_map');
        $this->setWidth(500);
        $this->setHeight(350);
        foreach ($locations as $entity) {
            $this->addMarker(new MapMarker($entity->getLatitude(), $entity->getLongitude()));
        }
    }
}