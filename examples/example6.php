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
	EXAMPLE 6
	In this example, 2 object entries are created.
	First one is static because the flag 'true' is not added to factory to make it dynamic, and so the value is cached.
	Second one is dynamic due to the 'true' added to the factory, so the value changes on every call for the same request.
*/
try {
	// create container instance
	$container = new Container();
	$container->set('gender', 'Male');
	$container->set('age', '30');
	$container->set('country', 'USA');
	$container->set('id.static', function (Container $c) {
		return uniqid();
	});
	$container->set('id.dynamic', function (Container $c) {
		return uniqid();
	});

	// STATIC
	// create static static entry value
	$container->set(Person::class, $container->factory(Person::class, ['gender', 'age'], 'getId', ['country', 'id.static']));

	// value remains the same
	echo "STATIC VALUE (VALUE STAYS THE SAME ON EVERY CALL FOR THE SAME REQUEST)";
	echo "<br/>";
	echo $container->get(Person::class);
	echo "<br/>";
	echo $container->get(Person::class);

	echo "<br/>";
	echo "<br/>";

	// DYNAMIC
	// create static static entry value
	$container->set(Person::class, $container->factory(Person::class, ['gender', 'age'], 'getId', ['country', 'id.dynamic'], true));

	// value remains the same
	echo "DYNAMIC VALUE (VALUE CHANGES ON EVERY CALL FOR THE SAME REQUEST)";
	echo "<br/>";
	echo $container->get(Person::class);
	echo "<br/>";
	echo $container->get(Person::class);

} catch(Exception $e) {
	die($e->getMessage());
}