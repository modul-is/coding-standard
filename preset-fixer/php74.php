<?php

declare(strict_types=1);

$config = require __DIR__ . '/php73.php';

$rules = [
	'@PHP7x4Migration' => true,
];

$config->setRules($rules + $config->getRules());
return $config;
