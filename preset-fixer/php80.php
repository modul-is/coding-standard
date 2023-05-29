<?php

declare(strict_types=1);

$config = require __DIR__ . '/php74.php';

$rules = [
	'@PHP80Migration' => true,
	'@PHP80Migration:risky' => true,
	'void_return' => false,

	// Convert class properties to PHP8
	'ModulIS/php8_class_property' => true,

	// Convert entity properties to PHP8
	'ModulIS/php8_entity_property' => true,

	// Rename entity Readonly attribute to ReadonlyProperty
	'ModulIS/entity_readonly' => true,

	// Convert constructors to PHP8
	'ModulIS/php8_constructor' => true,

	// Force spaces after named parameters
	'ModulIS/named_argument_space' => true
];

$config->setRules($rules + $config->getRules());
return $config;
