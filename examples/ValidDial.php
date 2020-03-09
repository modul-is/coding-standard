<?php

declare(strict_types=1);

namespace Nette\CodingStandard\Examples;


class ValidDial
{
	public const C1 = 'A';

	public const C2 = 'B';

	public const C3 = 'C';


	/**
	 * Translate constants
	 */
	private static function translate(): array
	{
		return [
			self::C1 => 'Aaa',
			self::C2 => 'Bbb',
			self::C3 => 'Ccc'
		];
	}


	/**
	 * Return name of constant
	 */
	public static function getString($key): string
	{
		return self::translate()[$key];
	}


	/**
	 * Return constant array with translation key
	 */
	public static function getList(): array
	{
		$reflection = new \ReflectionClass(static::class);

		$array = [];

		foreach($reflection->getConstants() as $key => $constant)
		{
			$array[$constant] = self::getString($constant);
		}

		return $array;
	}
}
