<?php

declare(strict_types=1);

namespace App\Router;

use Nette;
use Nette\Application\Routers\RouteList;



final class RouterFactory
{
	use Nette\StaticClass;
	
	public static function createRouter($maintenance): RouteList
	{
		$router = new RouteList;
		if ($maintenance) {
			// je udrzba, pokracuj na parkovaci stranku
			$router->addRoute('<presenter>/<action>[/<id>]', 'Udrzba:default');
		} else {
			// neni udrzba, pokracuj normalne na homepage
			$router->addRoute('<presenter>/<action>[/<id>]', 'Homepage:default');
		}
		return $router;
	}

}
