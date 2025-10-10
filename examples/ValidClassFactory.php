<?php

declare(strict_types = 1);

namespace Nette\CodingStandard\Examples;

interface ValidClassFactory
{
	public function create(): ValidClass;
}
