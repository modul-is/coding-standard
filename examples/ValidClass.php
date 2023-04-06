<?php

declare(strict_types=1);

namespace Nette\CodingStandard\Examples;

class ValidClass
{
	protected const CHILD_COUNT = 1,
	HOUSE_COUNT = 10; // allow comment

	private const DREAM_COUNT = 250;

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
		public array $listOfEmotions,
		array $listOfSkills,
		array $listOfElements
	)
	{
		$this->listOfSkills = $listOfSkills;
		$this->listOfElements = $listOfElements;
	}


	public function __destruct()
	{
		$a = '1';

		$b = fn($a) => (int) $a;
	}


	/**
	 * Foo
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

		if($anotherArgument === null)
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
		$string .= 'Object\\Record';

		echo "Metoda $string neexistuje";

		throw new \Exception("Instance of 'ModulIS\\Record' expected, '" . $string . "' given .");
	}


	private function internalMethod()
	{
		foreach($this->anotherMethod(1, 2) as $key => $value)
		{
			echo 'bla';
		}
	}


	private function callbackMethod($form)
	{
		$form->onSuccess[] = function() {};
	}
}
