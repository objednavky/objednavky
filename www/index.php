<?php

declare(strict_types=1);

ini_set('display_errors', '1'); error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

App\Bootstrap::boot()
	->createContainer()
	->getByType(Nette\Application\Application::class)
	->run();
