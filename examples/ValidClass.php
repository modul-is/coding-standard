<?php

declare(strict_types = 1);

namespace Nette\CodingStandard\Examples;

class ValidClass
{
	protected const
		A = 1,
		B = 2,
		C = 3;

	protected const CHILD_COUNT = 1, HOUSE_COUNT = 10; // allow comment

	private const DREAM_COUNT = 250;

	private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Safari/537.36';

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

		$c = 123456;

		$d = 1_234_567_890;

		$e = 1_234_567.123;

		$f = $a === '' ? null : (str_starts_with($a, '+420') ? substr($a, 4) : $a);

		if ($c >= $d || ($c !== null && $e <= $d))
		{
			$g = $d + $e;
		}
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
		elseif($sum)
		{
			$sum -= 5;
		}

		return (array) $sum;
	}


	protected function operatorMethod($one, $two, $three)
	{
		$a = $one ? $two : $three;
		$b = $one ?: $two;
		$c = $a ?? $b;
		$d = (new \DateTime)->format('Y-m-d H:i:s');

		return $c . $d;
	}


	protected function stringMethod($string)
	{
		preg_match('/\w+([\/\(\)])/', $string);

		$class = '\ModulIS\Example\\';
		$class .= 'Object\Record';
		$method = 'get' . ucfirst($string);

		echo 'Metoda ' . $class . '::' . $method . ' neexistuje';

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
