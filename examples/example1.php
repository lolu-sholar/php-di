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
	EXAMPLE 1
	In this example, an entry value is created as a dynamic object.
	The resolved value of a dynamic object is never cached because the
	object may have inner variables that keep changing, so it is re-run on every request.
*/
try {
	// create container instance
	$container = new Container();
	// create an entry with a reference to the Paypal API (can be easily changed to StripeApi::class)
	$container->set(PaymentService::class, $container->dynamic(PaypalApi::class));
	// create the customer object and pass the dependency
	$customer = new Customer($container->get(PaymentService::class));
	$customer->consumePaymentService((object) [
		'getId' => function(){
			return uniqid();
		}
	]);
} catch(Exception $e) {
	die($e->getMessage());
}