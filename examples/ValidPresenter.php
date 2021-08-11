<?php

declare(strict_types=1);

namespace Nette\CodingStandard\Examples;

use Nette\Application\UI\Presenter;
use Nette\Database\Explorer;
use Nette\DI\Attributes\Inject;
use Nette\Application\Attributes\Persistent;

class ValidPresenter extends Presenter
{
	/**
	 * @var string
	 * @persistent
	 */
	public $property;

	/**
	 * @var \My\Model
	 * @inject
	 */
	public $model;

	/**
	 * @var Explorer
	 * @inject
	 * @persistent
	 */
	public $database;
}
