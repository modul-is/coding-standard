<?php

declare(strict_types=1);

namespace ModulIS\Fixer\ClassNotation;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Preg;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class Php8ClassPropertyFixer extends AbstractFixer
{
	/**
	 * {@inheritdoc}
	 */
	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'Class properties should be written in PHP8 format.',
			[
				[new CodeSample('<?php
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
')]
			]
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * Must run after NoTrailingWhitespaceFixer
	 */
	public function getPriority(): int
	{
		return -1;
	}

	/**
	 * {@inheritdoc}
	 */
	public function isCandidate(Tokens $tokens): bool
	{
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
	{
		$content = $tokens->generateCode();

		$globalAttributeArray = [];

		$propertyPattern = '/\/\*\*((?:\R[ \t]*\*[ \t]*@(?:inject|persistent|var)[ \t\S]*)+)\R[ \t]*\*\/\R[ \t]*(\w+(?: static)?) (\$\w+(?: = [^;]*)?;)/s';
		$annotationPattern = '/@(inject|persistent|var)(?: (.+))?/';
		$usePattern = '/(namespace .*\R+)(use)?/';

		Preg::matchAll($propertyPattern, $content, $propertyArray, PREG_SET_ORDER);

		foreach($propertyArray as $property)
		{
			$dataType = '';
			$attributeArray = [];

			Preg::matchAll($annotationPattern, $property[1], $annotationArray, PREG_SET_ORDER);

			foreach($annotationArray as $annotation)
			{
				if($annotation[1] === 'var' && isset($annotation[2]))
				{
					if(strpos($annotation[2], '[]') !== false)
					{
						$dataType = (strpos($annotation[2], '?') !== false || strpos($annotation[2], 'null') !== false ? '?' : null) . 'array';
					}
					elseif(strpos($annotation[2], 'callable') !== false)
					{
						$dataType = 'array|\Closure' . (strpos($annotation[2], '?') !== false || strpos($annotation[2], 'null') !== false ? '|null' : null);
					}
					else
					{
						$dataType = trim($annotation[2]);
					}
				}
				elseif(in_array($annotation[1], ['inject', 'persistent'], true))
				{
					$attributeArray[] = ucfirst($annotation[1]);

					if(!in_array($annotation[1], $globalAttributeArray, true))
					{
						$globalAttributeArray[] = $annotation[1];
					}
				}
			}

			$output = '';

			if($attributeArray)
			{
				$output .= '#[' . implode(', ', $attributeArray) . ']' . PHP_EOL . "\t";
			}

			$output .= $property[2] . ' ';
			$output .= $dataType ? $dataType . ' ' : null;
			$output .= $property[3];

			$content = str_replace($property[0], $output, $content);
		}

		foreach($globalAttributeArray as $attribute)
		{
			if($attribute === 'inject')
			{
				$path = 'Nette\DI\Attributes\Inject';
			}
			elseif($attribute === 'persistent')
			{
				$path = 'Nette\Application\Attributes\Persistent';
			}

			if(isset($path) && strpos($content, 'use ' . $path) === false)
			{
				Preg::match($usePattern, $content, $matches);
				$content = Preg::replace($usePattern, $matches[1] . 'use ' . $path . ';' . PHP_EOL . ($matches[2] ?? PHP_EOL), $content);
			}
		}

		$newTokens = Tokens::fromCode($content);

		foreach($newTokens as $index => $token)
		{
			$newTokens[$index] = new Token($token->getContent());
		}

		$tokens->overrideRange(0, $tokens->count() - 1, $newTokens);
	}


	public function getName(): string
	{
		return 'ModulIS/' . parent::getName();
	}
}
