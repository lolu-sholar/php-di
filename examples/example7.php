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
	EXAMPLE 7
	In this example, all entries are passed as definitions to the constructor
*/
try {
	// definitions
	$definitions = [
		'person.gender' 	=> 	'Male',
		'person.age' 			=> 	'23',
		'person.country' 	=> 	'USA',
		'id' 							=>	function (Container $c) {
			return uniqid();
		}
	];

	// create container instance
	$container = new Container($definitions);
	$container->set(Person::class, $container->factory(Person::class, ['person.gender', 'person.age'], 'getId', ['person.country', 'id']));

	echo $container->get(Person::class);

} catch(Exception $e) {
	die($e->getMessage());
}