<?php

declare(strict_types = 1);

namespace Nette\CodingStandard\Examples;

use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Presenter;
use Nette\Database\Explorer;
use Nette\DI\Attributes\Inject;

class ValidPresenter extends Presenter
{
	#[Persistent]
	public string $property;

	#[Inject]
	public \My\Model $model;

	#[Inject, Persistent]
	public Explorer $database;

	private static array $array = [1, 2, 3];
}
