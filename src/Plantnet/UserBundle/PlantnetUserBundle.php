<?php

namespace Plantnet\UserBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class PlantnetUserBundle extends Bundle
{
    public function getParent()
	{
		return 'FOSUserBundle';
	}
}
