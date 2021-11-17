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
	EXAMPLE 3
	In this example, an entry value is created from a normal object.
	The resolved value is cached and remains the same for the life of the request.
*/
try {
	// create container instance
	$container = new Container();
	$container->set('person.gender', 'Female');
	$container->set('person.age', '45');
	$container->set('country', 'Nigeria');
	$container->set(Person::class, $container->create(Person::class)
																					->constructor(['person.gender', 'person.age'])
																					->method('details')
																					->arguments(['country']));
	echo $container->get(Person::class);

	// change some definitions
	$container->set('person.age', '50');
	$container->set('country', 'Spain');

	echo "<br/>";

	echo $container->get(Person::class);
} catch(Exception $e) {
	die($e->getMessage());
}