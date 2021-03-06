<?php

declare(strict_types=1);

namespace App;

use Nette\Configurator;	


class Bootstrap
{
	public static function boot(): Configurator
	{
		$configurator = new Configurator;

		// enable debug if there is .debug file in application root dir
		$configurator->setDebugMode(file_exists(__DIR__ . '/../.debug')); 
		$configurator->enableTracy(__DIR__ . '/../log');

		$configurator->setTimeZone('Europe/Prague');
		$configurator->setTempDirectory(__DIR__ . '/../temp');

		$configurator->createRobotLoader()
			->addDirectory(__DIR__)
			->register();

		$configurator->addConfig(__DIR__ . '/config/local.neon');
		$configurator->addConfig(__DIR__ . '/config/common.neon');

		return $configurator;
	}
}
