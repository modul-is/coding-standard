<?php

declare(strict_types=1);

namespace Nette\CodingStandard\Examples;

class ValidEntity extends \ModulIS\Entity
{
	public int $id;

	public int|null $number;

	public string $text;

	public string|null $other_text;

	public json $data;
}
