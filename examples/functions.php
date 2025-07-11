<?php

declare(strict_types = 1);

$x = function($x, $y)
{
	echo $x + $y;
};

$x = fn($y) => $x + $y;

$x = fn($x, $y) => $x + $y;
