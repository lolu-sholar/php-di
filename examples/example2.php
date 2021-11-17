<?php

declare(strict_types=1);

// ini settings
ini_set('display_errors', 'Off');
ini_set('display_startup_errors', 'Off');
ini_set('error_reporting', (string)E_ALL);
ini_set('log_errors', 'On');

use Vader\DI\Container;

require_once '../vendor/autoload.php';
require_once '../src/Container.php';
require_once '../src/NotFoundException.php';
require_once 'classes.php';

/*
	EXAMPLE 2
	In this example, an entry value is created from a normal object.
	The resolved value is cached and remains the same for the life of the request.
*/
try {
	// create container instance
	$container = new Container();
	$container->set('default.breed', 'Pomeranian');
	$container->set('default.color', 'Yellow');
	$container->set('DogBreed', $container->create(Dog::class, ['default.breed'])
																					->method('whatBreed', ['default.color']));
	echo $container->get('DogBreed');
} catch(Exception $e) {
	die($e->getMessage());
}