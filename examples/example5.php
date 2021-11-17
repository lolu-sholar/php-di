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
	EXAMPLE 5
	In this example, an entry value is created from a normal object.
	The resolved value is cached and remains the same for the life of the request.
*/
try {
	// create container instance
	$container = new Container();
	$container->set('gender', 'Male');
	$container->set('age', '30');
	$container->set('country', 'USA');
	$container->set('id', function (Container $c) {
		return uniqid();
	});
	$container->set(Person::class, $container->factory(Person::class, ['gender', 'age'], 'getId', ['country', 'id']));

	echo $container->get(Person::class);
} catch(Exception $e) {
	die($e->getMessage());
}