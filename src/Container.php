<?php

declare(strict_types=1);

namespace Vader\DI;

use Vader\DI\Exception\NotFoundException;
use Psr\Container\ContainerInterface;
use Psr\Container\ContainerExceptionInterface;

class Container implements ContainerInterface
{
	// array store for all defined entries
	private $entriesAll = [];
	// array store for all entries data types
	private $entriesDataTypes = [];
	// array store for resolved entries
	private $entriesResolved = [];
	// scope properties store for possible entry
	private $entryScope = null;
	// array store for hashes for entries
	private $entriesHashMap = [];
	// array store for entries dependencies
	private $entriesDependencies = [];
	// array store for entries computed hash
	private $entriesComputedHash = [];
	// array store for entries that are part of a dynamic scope
	private $entryIdsOfDynamicScope = [];
	// object of type definitions
	private $dataTypeDefinitions = [
		'integer' => 'int',
		'boolean' => 'bool',
		'double' 	=> 'float'
	];

	/**
	 * Object constructor
	 */
	public function __construct(array $definitions = [])
	{
		// build array to contain entries and add this container to entries
		$this->entriesAll = [ContainerInterface::class => $this];
		// create entries for initial definitions
		$this->initDefinitions($definitions);
		// initialize scope for possible entry
		$this->initScope();
		// generate hashes for entries
		$this->generateHashValues();
	}

	/**
	 * Initialize all defined scope properties
	 */
	private function initScope()
	{
		// initialize scope properties
		$this->entryScope = (object) [
			'object' 				=> 	'',
			'constructor' 	=> 	[],
			'method' 				=> 	'',
			'arguments' 		=> 	[],
			'dynamic' 			=> 	false
		];
	}

	/**
	 * Initialize default definitions passed to constructor
	 *
	 * @param array Array of definitions passed by default to constructor
	 */
	public function initDefinitions(array $definitions)
	{
		try{
			// loop through definitions and create entries for them
			foreach ($definitions as $key => $value) {
				// create entry for definition
				$this->set($key, $value);
			}
		}catch(Exception $e) {
			die($e->getMessage());
		}
	}

	/**
	 * Generate hash values for all entry identifiers stored.
	 *
	 * @param array $values Array of identifiers to generate hash values for
	 */
	private function generateHashValues(array $identifiers = [])
	{
		try{
			// loop through all entries and generate hash values
			foreach ($identifiers as $value) {
				// fill hash map with value
				$this->entriesHashMap[$value] = md5(uniqid());
			}
		}catch(Exception $e) {
			return $e->getMessage();
		}
	}

	/**
   * Finds an entry of the container by its identifier and returns it.
   *
   * @param string $id Identifier of the entry to look for.
   *
   * @throws NotFoundExceptionInterface  No entry was found for **this** identifier.
   * @throws ContainerExceptionInterface Error while retrieving the entry.
   *
   * @return mixed Entry.
   */
	public function get(string $identifier = '')
	{
		try {
			// context variable
			$context = (object)[
				'constructor' => (object)[
					'args' 		=> []
				],
				'method' 		=> (object)[
					'args' 		=> []
				],
				'container'	=> false
			];

			// if entry store does not contains entry with the identifier
			if($this->has($identifier) === false) {
				// throw exception
				throw new NotFoundException("No entry found for identifier: '$identifier'.");
			}

			// check if entry value is closure or container
			if((gettype($this->entriesAll[$identifier]) == 'object') and ($this->entriesAll[$identifier] instanceof Container)) {
				// recompute dependency hash for entry
				$context->recomputedHash = $this->recomputeDependencyHash($identifier);
				// check if previous hash matches new computed hash
				if($context->recomputedHash <> $this->entriesComputedHash[$identifier]) {
					// store new hash
					$this->entriesComputedHash[$identifier] = $context->recomputedHash;
					// check if entry has been resolved
					if(array_key_exists($identifier, $this->entriesResolved)) {
						// unset from store of resolved entries
						unset($this->entriesResolved[$identifier]);
					}
				}
			}

			// check if entry has been resolved
			if(array_key_exists($identifier, $this->entriesResolved)) {
				// get and return value from resolved entries store
				return $this->entriesResolved[$identifier];
			} else if(array_key_exists($identifier, $this->entriesAll)) {
				// get value from all entries store
				$value = $this->entriesAll[$identifier];
				// check if value is an instance of closure
				if($value instanceof \Closure) {
					// call closure
					$value = $value($this);
				} else if ((gettype($value) == 'object') and ($value instanceof Container)) {
					// identify value as container
					$context->container = true;
					// get scope
					$context->scope = $value->scope();
					// create reflection class object
					$context->rClass = new \ReflectionClass($context->scope->object);
					// switch case
					switch($this->translateScope($context->scope)){
						case 1:
							// get instance of class
							$value = $context->rClass->newInstance();
							break;
						case 2:
							// get arguments
							$context->constructor->args = $this->loadDefintions($context->scope->constructor);
							// get instance of class
							$value = $context->rClass->newInstanceArgs($context->constructor->args);
							break;
						case 3:
							// get arguments
							$context->constructor->args = $this->loadDefintions($context->scope->constructor);
							// create reflection method object
							$context->rMethod = $context->rClass->getMethod($context->scope->method);
							// get instance of class
							$value = $context->rMethod->invoke($context->rClass->newInstanceArgs($context->constructor->args));
							break;
						case 4:
							// get arguments
							$context->constructor->args = $this->loadDefintions($context->scope->constructor);
							$context->method->args = $this->loadDefintions($context->scope->arguments);
							// create reflection method object
							$context->rMethod = $context->rClass->getMethod($context->scope->method);
							// get instance of class
							$value = $context->rMethod->invokeArgs($context->rClass->newInstanceArgs($context->constructor->args), $context->method->args);
							break;
						case 5:
							// create reflection method object
							$context->rMethod = $context->rClass->getMethod($context->scope->method);
							// get instance of class
							$value = $context->rMethod->invoke($context->rClass->newInstance());
							break;
						case 6:
							// get arguments
							$context->method->args = $this->loadDefintions($context->scope->arguments);
							// create reflection method object
							$context->rMethod = $context->rClass->getMethod($context->scope->method);
							// get instance of class
							$value = $context->rMethod->invokeArgs($context->rClass->newInstance(), $context->method->args);
							break;
					}
				}
			}

			// check if value is empty
			if($context->container) {
				// check if behaviour of scope is not dynamic and value is not empty
				if(($context->scope->dynamic == false) and !empty($value)) {
					// add new entry to resolved entries store
					$this->entriesResolved[$identifier] = $value;
				}
			} else {
				// check if entry is part of any dynamic scope
				if(!in_array($identifier, $this->entryIdsOfDynamicScope)) {
					// add new entry to resolved entries store
					$this->entriesResolved[$identifier] = $value;
				}
			}

			// return value
			return $value;
		}catch(Exception $e) {
			return $e->getMessage();
		}
	}

	/**
	 * Load all values mapped to entries/definitions
	 *
	 * @param array $definitions Array list of definitions whose values to return
	 *
	 * @return array Array list of values mapped to defined identifiers
	 */
	private function loadDefintions(array $definitions = []) : array
	{
		try{
			// context variable
			$context = (object)[
				'values' => []
			];

			// loop through definitions and get their values
			foreach ($definitions as $value) {
				// feed defined values into array
				$context->values[] = $this->get($value);
			}

			// return the array containing defined values
			return $context->values;
		}catch(Exception $e) {
			return $e->getMessage();
		}
	}

	/**
   * Translates the scope object to an integer for processing scope components properly.
   *
   * @param object $scope Object containing scope components
   *
   * @return int
   */
	private function translateScope(object $scope) : int
	{
		try {
			// check if class alone is set
			if ((count($scope->constructor) < 1) and empty($scope->method) and (count($scope->arguments) < 1)) {
				return 1;
			} else if ((count($scope->constructor) > 0) and empty($scope->method) and (count($scope->arguments) < 1)) {
				return 2;
			} else if ((count($scope->constructor) > 0) and !empty($scope->method) and (count($scope->arguments) < 1)) {
				return 3;
			} else if ((count($scope->constructor) > 0) and !empty($scope->method) and (count($scope->arguments) > 0)) {
				return 4;
			} else if ((count($scope->constructor) < 1) and !empty($scope->method) and (count($scope->arguments) < 1)) {
				return 5;
			} else if ((count($scope->constructor) < 1) and !empty($scope->method) and (count($scope->arguments) > 0)) {
				return 6;
			}

			// return 1 by default
			return 10;
		}catch(Exception $e) {
			return $e->getMessage();
		}
	}

	/**
   * Returns true if the container can return an entry for the given identifier.
   * Returns false otherwise.
   *
   * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
   * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
   *
   * @param string $id Identifier of the entry to look for.
   *
   * @return bool
   */
	public function has(string $identifier) : bool
	{
		// check if identifier exists in stores for resolved or all entries
		if(array_key_exists($identifier, $this->entriesResolved) or array_key_exists($identifier, $this->entriesAll)) {
			return true;
		}
		return false;
	}

	/**
	 * Maps an identifier to a container entry
	 *
	 * @param string $identifier Identifier of the entry to store.
	 * @param mixed $value Entry value to be mapped to identifier.
	 */
	public function set(string $identifier = '', $value = null)
	{
		try {
			// context variable
			$context = (object)[];

			// trim identifier
			$identifier = trim($identifier);

			// check that identifier is not invalid
			if(empty($identifier)) {
				// throw exception
				throw new NotFoundException("No entry identifier defined!");
			}

			// check if entry value is container object and validate object constructor arguments count
			if($value instanceof Container) {
				// get scope
				$context->scope = $value->scope();
				// get reflection method
				$context->reflection = new \ReflectionMethod($context->scope->object, '__construct');
				// check that number of arguments passed is the same as parameters expected
				if($context->reflection->getNumberOfParameters() <> count($context->scope->constructor)){
					// throw exception
					throw new NotFoundException("Invalid number of arguments. Constructor of class '{$context->scope->object}' expects {$context->reflection->getNumberOfParameters()} argument(s) and not " . count($context->scope->constructor) . "!");
				}

				// check if method is provided
				if(!empty(trim($context->scope->method))){
					// get reflection method
					$context->reflection = new \ReflectionMethod($context->scope->object, $context->scope->method);
					// check that number of arguments passed is the same as parameters expected
					if($context->reflection->getNumberOfParameters() <> count($context->scope->arguments)){
						// throw exception
						throw new NotFoundException("Invalid number of arguments. Method '{$context->scope->method}' of class '{$context->scope->object}' expects {$context->reflection->getNumberOfParameters()} argument(s) and not " . count($context->scope->arguments) . "!");
					}
				}

				// validate types of arguments passed to class contained in scope
				$this->validateArgumentsTypeForScopeObject($value);

				// get and store return type for scope
				$this->processAndStoreReturnTypeForScope($identifier, $value);

				// load dependency configuration for entry
				$this->loadDependencyConfiguration($identifier, $context->scope);
			}else {
				// store data type for value
				$this->entriesDataTypes[$identifier] = $this->dataTypeDefinitions[gettype($value)] ?? gettype($value);
				// check if object is a function
				if (($this->entriesDataTypes[$identifier] == 'object') and $value instanceof \Closure){
					// adjust the type of the value
					$this->entriesDataTypes[$identifier] = 'closure';
				}
			}

			// check if identifier has already been resolved
			if(array_key_exists($identifier, $this->entriesResolved)) {
				// remove identifier from resolved entries
				unset($this->entriesResolved[$identifier]);
			}

			// check if value passed is a scoped object
			$this->entriesAll[$identifier] = $value;
			// generate hash value for new identifier
			$this->generateHashValues([$identifier]);
		}catch(Exception $e){
			return $e->getMessage();
		}
	}

	/**
	 * Validate data types of arguments passed to class constructor and method
	 *
	 * @param Container $container Container object containing scope data
	 */
	private function validateArgumentsTypeForScopeObject(Container $container)
	{
		try{
			// context variable
			$context = (object)[];

			// get scope object data
			$context->scope = $container->scope();

			// reflection class
			$context->rClass = new \ReflectionClass($context->scope->object);

			// check if arguments are passed to constructor
			if (count($context->scope->constructor) > 0) {
				// get all parameters passed to constructor
				$context->constructorParameters = $context->rClass->getMethod('__construct')->getParameters();
				// loop through definitions passed
				for ($i = 0; $i < count($context->scope->constructor); $i++) {
					// get parameter position
					$context->parameterPosition = $i + 1;
					// get type of argument passed
					$context->argumentType = $this->entriesDataTypes[$context->scope->constructor[$i]] ?? 'NOTHING';
					// get type expected by parameter
					$context->parameterType = $context->constructorParameters[$i]->getType();
					// check if type of argument is same as parameter expecting
					if ($context->argumentType <> $context->parameterType){
						// throw exception
						throw new NotFoundException("Parameter {$context->parameterPosition} of '{$context->scope->object}::__construct()' expects type '$context->parameterType' but '$context->argumentType' was passed.");
					}
				}
			}

			// check if method is defined and arguments are passed for method
			if(!empty($context->scope->method) and count($context->scope->arguments) > 0) {
				// get all parameters passed to method
				$context->methodParameters = $context->rClass->getMethod($context->scope->method)->getParameters();
				// loop through definitions passed
				for ($i = 0; $i < count($context->scope->arguments); $i++) {
					// get parameter position
					$context->parameterPosition = $i + 1;
					// get type of argument passed
					$context->argumentType = $this->entriesDataTypes[$context->scope->arguments[$i]] ?? 'NOTHING';
					// get type expected by parameter
					$context->parameterType = $context->methodParameters[$i]->getType();
					// check if type of argument is same as parameter expecting
					if (($context->argumentType <> $context->parameterType) and ($context->argumentType <> 'closure')) {
						// throw exception
						throw new NotFoundException("Parameter {$context->parameterPosition} of '{$context->scope->object}::{$context->scope->method}()' expects type '$context->parameterType' but '$context->argumentType' was passed.");
					}
				}
			}
		}catch(Exception $e) {
			return $e->getMessage();
		}
	}

	/**
	 * Process and store the return type of object passed as entry value
	 *
	 * @param string $identifier Identifier name mapped to entry
	 * @param Container $container Container object containing scope data
	 */
	private function processAndStoreReturnTypeForScope(string $identifier, Container $container)
	{
		try{
			// context variable
			$context = (object)[];

			// get scope
			$context->scope = $container->scope();

			// switch case
			switch ($this->translateScope($context->scope)) {
				case 1:
				case 2:
					// get name of object as type
					$context->type = $context->scope->object;
					break;
				case 3:
				case 4:
				case 5:
				case 6:
					// get reflection method
					$context->rMethod = new \ReflectionMethod($context->scope->object, $context->scope->method);
					// get return type
					$context->type = ($context->rMethod->getReturnType() instanceof \ReflectionNamedType)
						? $context->rMethod->getReturnType()->getName()
						: $context->rMethod->getReturnType();
					break;
			}

			// store data type for value
			$this->entriesDataTypes[$identifier] = $context->type;
		}catch(Exception $e) {
			return $e->getMessage();
		}
	}

	/**
	 * Recompute hashes of all dependencies mapped to an entry
	 *
	 * @param string $identifier Identifier mapped to entry
	 *
	 * @return stirng Computed hash for all dependencies of entry mapped to identifier
	 */
	private function recomputeDependencyHash(string $identifier = '') : string
	{
		try{
			// context variable
			$context = (object)[
				'concatenation' => ''
			];

			// loop through dependencies of identifier
			foreach ($this->entriesDependencies[$identifier] as $value) {
				// concatenate hash values for values
				$context->concatenation .= $this->entriesHashMap[$value];
			}

			// return computed hash
			return md5($context->concatenation);
		}catch(Exception $e) {
			return $e->getMessage();
		}
	}

	/**
	 * Compute and store combined hash value for all dependencies of entry mapped to identifier
	 *
	 * @param string $identifier Identifer mapped to entry
	 * @param object $scope Object scope data for entry
	 */
	private function loadDependencyConfiguration(string $identifier = '', object $scope = null)
	{
		try{
			// context variable
			$context = (object)[
				'concatenation' => ''
			];

			// merge all dependencies
			$context->dependencies = array_merge($scope->constructor, $scope->arguments);

			// loop through dependencies
			foreach ($context->dependencies as $value) {
				// concatenate hashes
				$context->concatenation .= $this->entriesHashMap[$value];
			}

			// store the computed hash of all dependencies for identifier
			$this->entriesComputedHash[$identifier] = md5($context->concatenation);

			// store all dependencies for entry
			$this->entriesDependencies[$identifier] = $context->dependencies;

			// check if scope is dynamic
			if($scope->dynamic) {
				// add dependencies to store
				$this->entryIdsOfDynamicScope = array_merge($this->entryIdsOfDynamicScope, $context->dependencies);
			}
		}catch(Exception $e) {
			return $e->getMessage();
		}
	}

	/**
	 * Create a dynamic object scope whose resolved value is never cached
	 *
	 * @param string $scope Name of object to map to entry identifier
	 * @param array $args Array list of arguments to pass to object constructor
	 *
	 * @return object Container object
	 */
	public function dynamic(string $scope = '', array $args = []) : Container
	{
		try{
			// create dynamic object scope
			return $this->create($scope, $args, true);
		}catch(Exception $e){
			return $e->getMessage();
		}
	}

	/**
	 * Initiate creation of scope components to add as container entry.
	 *
	 * @param mixed $scope Name of the class object or an array containing just class object name or both object and method names
	 *
	 * @return object Container object
	 */
	public function create(string $scope = '', array $args = [], bool $dynamic = false) : Container
	{
		try{
			// flush scope
			$this->initScope();

			// create context variable
			$context = (object)[];

			// check if scope class and method contain valid data
			if (in_array($scope, [null, ''])) {
				// throw exception
				throw new NotFoundException("Object name not found!");
			}

			// extract the object name
			$context->object = $scope;

			// check that provided class exists
			if(!class_exists($context->object)) {
				// throw exception
				throw new NotFoundException("Class with name '$context->object' does not exist!");
			}

			// check if arguments list was passed
			if(func_num_args() > 1) {
				// get reflection method
				$context->reflection = new \ReflectionMethod($context->object, '__construct');
				// check that number of arguments passed is the same as parameters expected
				if($context->reflection->getNumberOfParameters() <> count($args)){
					// throw exception
					throw new NotFoundException("Invalid number of arguments. Constructor of class '{$context->object}' expects {$context->reflection->getNumberOfParameters()} argument(s) and not " . count($args) . "!");
				}

				// define constructor arguments for object scope
				$this->entryScope->constructor = $args;
			}

			// define scope properties
			$this->entryScope = (object)[
				'object' 				=> $context->object,
				'constructor' 	=> $this->entryScope->constructor,
				'method' 				=> $this->entryScope->method,
				'arguments' 		=> $this->entryScope->arguments,
				'dynamic' 			=> $dynamic
			];

			// return container
			return $this;
		}catch(Exception $e) {
			return $e->getMessage();
		}
	}

	/**
	 * Provide arguments to be passed to class constructor when initiating creation of scope components.
	 *
	 * @param array $args Array containing list of arguments to be passed to class constructor
	 *
	 * @return object Container object
	 */
	public function constructor(array $args = []) : Container
	{
		try{
			// context variable
			$context = (object)[];

			// flag exception if method is defined without constructor
			if(empty($this->entryScope->object)) {
				// throw exception
				throw new NotFoundException("Class object not defined! You should call the 'Container::create()' closure first and then the 'Container::constructor()' to define the arguments list if any.");
			} else if($this->entryScope->method != '') {
				// throw exception
				throw new NotFoundException("Constructor arguments not found! You should call the 'Container::constructor()' closure first and then the 'Container::method()' to define the object method.");
			}

			// get reflection method
			$context->reflection = new \ReflectionMethod($this->entryScope->object, '__construct');
			// check that number of arguments passed is the same as parameters expected
			if($context->reflection->getNumberOfParameters() <> count($args)){
				// throw exception
				throw new NotFoundException("Invalid number of arguments. Constructor of class '{$this->entryScope->object}' expects {$context->reflection->getNumberOfParameters()} argument(s) and not " . count($args) . "!");
			}

			// define constructor arguments for object scope
			$this->entryScope->constructor = $args;

			// return container object
			return $this;
		}catch(Exception $e) {
			return $e->getMessage();
		}
	}

	/**
	 * Provide class method name as part of scope components being created.
	 *
	 * @param string $name Name of the class method
	 *
	 * @return object Container object
	 */
	public function method(string $name = '', array $args = []) : Container
	{
		try{
			// context variable
			$context = (object)[];

			// check that method name is defined
			if(empty(trim($name))) {
				// throw exception
				throw new NotFoundException("Method name not found!");
			}

			// flag exception if method is defined without constructor
			if(empty($this->entryScope->object)) {
				// throw exception
				throw new NotFoundException("Class object not defined! You should call the 'Container::create()' closure first to define class object.");
			}

			// check that method exists within class
			if(!method_exists($this->entryScope->object, trim($name))) {
				// throw exception
				throw new NotFoundException("Method '$name' does not exist for class '{$this->entryScope->object}'.");
			}

			// check if arguments list was passed
			if(func_num_args() > 1) {
				// get reflection method
				$context->reflection = new \ReflectionMethod($this->entryScope->object, trim($name));
				// check that number of arguments passed is the same as parameters expected
				if($context->reflection->getNumberOfParameters() <> count($args)){
					// throw exception
					throw new NotFoundException("Invalid number of arguments. Method '" . trim($name) . "' of class '{$this->entryScope->object}' expects {$context->reflection->getNumberOfParameters()} argument(s) and not " . count($args) . "!");
				}

				// define method arguments for object scope
				$this->entryScope->arguments = $args;
			}

			// define method name for object scope
			$this->entryScope->method = trim($name);

			// return container object
			return $this;
		}catch(Exception $e){
			return $e->getMessage();
		}
	}

	/**
	 * Provide arguments to be passed to class method when initiating creation of scope components.
	 *
	 * @param array $args Array containing list of arguments to be passed to class method
	 *
	 * @return object Container object
	 */
	public function arguments(array $args = []) : Container
	{
		try{
			// context variable
			$context = (object)[];

			// flag exception if method is defined without constructor
			if($this->entryScope->method == '') {
				// throw exception
				throw new NotFoundException("Method name not found! You should call the 'Container::method()' closure first before defining the method arguments by calling 'Container::arguments()'.");
			}

			// get reflection method
			$context->reflection = new \ReflectionMethod($this->entryScope->object, $this->entryScope->method);
			// check that number of arguments passed is the same as parameters expected
			if($context->reflection->getNumberOfParameters() <> count($args)) {
				// throw exception
				throw new NotFoundException("Invalid number of arguments. Method '{$this->entryScope->method}' of class '{$this->entryScope->object}' expects {$context->reflection->getNumberOfParameters()} argument(s) and not " . count($args) . "!");
			}

			// define constructor arguments for object scope
			$this->entryScope->arguments = $args;

			// return container object
			return $this;
		}catch(Exception $e) {
			return $e->getMessage();
		}
	}

	/**
	 * Returns the object data containing the scope components to add as container entry.
	 *
	 * @return object Object containing the scope data
	 */
	private function scope() : object
	{
		try{
			// return scope object
			return $this->entryScope;
		}catch(Exception $e){
			return $e->getMessage();
		}
	}

	/**
	 * Build scope components to add as container entry.
	 *
	 * @param string $object Name of the class object
	 * @param array $constructor Arguments array list to pass to class constructor
	 * @param string $method Name of class method to call
	 * @param array $arguments Arguments array list to pass to class method
	 *
	 * @return object Container object
	 */
	public function factory(string $object = '', array $constructor = [], string $method = '', array $arguments = [], bool $dynamic = false) : Container
	{
		try{
			// flush scope
			$this->initScope();

			// create context object
			$context = (object)[
				'object' 			=> trim($object),
				'method' 			=> trim($method),
				'reflection' 	=> null
			];

			// check that object name is valid
			if(empty($context->object)) {
				// throw exception
				throw new NotFoundException("Object name not found!");
			}

			// check that provided class exists
			if(!class_exists($context->object)) {
				// throw exception
				throw new NotFoundException("Class with name '$context->object' does not exist!");
			} else {
				// get reflection method
				$context->reflection = new \ReflectionMethod($context->object, '__construct');
				// check that number of arguments passed is the same as parameters expected
				if($context->reflection->getNumberOfParameters() <> count($constructor)){
					// throw exception
					throw new NotFoundException("Invalid number of arguments. Constructor of class '$context->object' expects {$context->reflection->getNumberOfParameters()} argument(s) and not " . count($constructor) . "!");
				}
			}

			// check that method was defined
			if(!empty($context->method)) {
				// check that class method exists
				if(!method_exists($context->object, $context->method)){
					// throw exception
					throw new NotFoundException("Method '$context->method' does not exist in class '$context->object'!");
				} else {
					// get reflection method
					$context->reflection = new \ReflectionMethod($context->object, $context->method);
					// check that number of arguments passed is the same as parameters expected
					if($context->reflection->getNumberOfParameters() <> count($arguments)){
						// throw exception
						throw new NotFoundException("Invalid number of arguments. Method '$context->method' of class '$context->object' expects {$context->reflection->getNumberOfParameters()} argument(s) and not " . count($arguments) . "!");
					}
				}
			} else if(count($arguments) > 0) {
				// check to ensure method name is valid
				if(empty($context->method)) {
					// throw exception
					throw new NotFoundException("Method arguments defined but method name not found!");
				}
			}

			// define scope properties
			$this->entryScope = (object)[
				'object' 				=> $context->object,
				'constructor' 	=> $constructor,
				'method' 				=> $context->method,
				'arguments' 		=> $arguments,
				'dynamic' 			=> $dynamic
			];

			// return container object
			return $this;
		}catch(Exception $e) {
			return $e->getMessage();
		}
	}
}