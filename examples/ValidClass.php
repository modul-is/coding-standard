<?php

declare(strict_types=1);

namespace Nette\CodingStandard\Examples;

class ValidClass
{
	protected const CHILD_COUNT = 1,
		HOUSE_COUNT = 10; // allow comment

	private const DREAM_COUNT = 250;

	/**
	 * @var array
	 */
	public $listOfEmotions = [
		'love',
		'happiness'
	];

	protected $listOfSkills = [
		'empathy',
		'respect'
	];

	private $listOfElements = [
		'Nette',
		'Latte'
	];


	public function __construct
	(
		array $listOfEmotions
	)
	{
		$this->listOfEmotions = $listOfEmotions;
	}


	public function __destruct()
	{
		$a = '1';

		$b = function($a)
		{
			return (int) $a;
		};
	}


	/**
	 * Foo
	 *
	 * @todo Add bar
	 */
	public function validMethod(): int
	{
		$a = 0;

		while($a < 10)
		{
			if($a)
			{
				$a++;
			}
			else
			{
				$a--;
			}
		}

		return $a;
	}


	protected function anotherMethod($someArgument, $anotherArgument)
	{
		$sum = $someArgument + $anotherArgument;

		if(is_null($anotherArgument))
		{
			$sum += 5;
		}

		return (array) $sum;
	}


	protected function operatorMethod($one, $two, $three)
	{
		$a = $one ? $two : $three;
		$b = $one ?: $two;
		$c = $a ?? $b;

		return $c;
	}


	protected function stringMethod($string)
	{
		preg_match('/\w+([\/\(\)])/', $string);

		$string .= '\ModulIS\Example\\';
		$string .= "Object\Record";

		echo "Metoda $string neexistuje";

		throw new \Exception("Instance of 'ModulIS\Record' expected, '" . $string . "' given .");
	}


	private function internalMethod()
	{
		foreach($this->anotherMethod(1, 2) as $key => $value)
		{
			echo 'bla';
		}
	}
}
