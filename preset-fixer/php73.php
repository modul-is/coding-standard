<?php

declare(strict_types=1);

$config = require __DIR__ . '/php72.php';

$rules = [
	'@PHP73Migration' => true,
];

$config->setRules($rules + $config->getRules());
return $config;
