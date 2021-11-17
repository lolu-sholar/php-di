<?php

declare(strict_types=1);

// payment interface
interface PaymentService
{
	public function getPaymentDetails($id);
}

// paypal service
class PaypalApi implements PaymentService
{
	public function __construct() {}

	public function getPaymentDetails($id)
	{
		echo "ID: '$id' handled by Paypal API!";
	}
}

// stripe service
class StripeAPi implements PaymentService
{
	public function __construct() {}

	public function getPaymentDetails ($id)
	{
		echo "ID: '$id' handled by Stripe API!";
	}
}

// class customer
class Customer
{
	private $paymentService;

	public function __construct(PaymentService $paymentService)
	{
		$this->paymentService = $paymentService;
	}

	public function consumePaymentService($customer)
	{
		$this->paymentService->getPaymentDetails(call_user_func($customer->getId));
	}
}

// class dog
class Dog
{
	private $breed;

	public function __construct(string $breed)
	{
		$this->breed = $breed;
	}

	public function whatBreed (string $color) : string
	{
		return 'Dog breed is ' . $this->breed . ', and color is ' . $color . '.';
	}
}

// class person
class Person
{
	private $gender;
	private $age;

	public function __construct(string $gender, string $age)
	{
		$this->gender = $gender;
		$this->age = $age;
	}

	public function details (string $country) : string
	{
		return 'Person is a ' . $this->age . ' year old ' . $this->gender . ', and is from ' . $country . '.';
	}

	public function getId (string $country, string $id) : string
	{
		return $this->details($country) . '<br/>Unique id for this person is ' . $id . '.';
	}
}