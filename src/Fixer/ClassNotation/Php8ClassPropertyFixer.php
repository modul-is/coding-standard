<?php

declare(strict_types=1);

namespace Nette\CodingStandard\Fixer\ClassNotation;

use Nette\Utils\Strings;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Preg;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class Php8ClassPropertyFixer extends AbstractFixer
{
	/**
	 * {@inheritdoc}
	 */
	public function getDefinition()
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
	public function getPriority()
	{
		return -1;
	}

	/**
	 * {@inheritdoc}
	 */
	public function isCandidate(Tokens $tokens)
	{
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function applyFix(\SplFileInfo $file, Tokens $tokens)
	{
		$content = $tokens->generateCode();

		$globalAttributeArray = [];

		$propertyPattern = '/\/\*\*((?:\R[ \t]*\*[ \t]*@(?:inject|persistent|var)[ \t\S]*)+)\R[ \t]*\*\/\R[ \t]*(\w+) (\$\w+;)/';
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
					if(Strings::contains($annotation[2], '[]'))
					{
						$dataType = (Strings::contains($annotation[2], 'null') ? '?' : null) . 'array';
					}
					else
					{
						$dataType = trim($annotation[2]);
					}
				}
				elseif(in_array($annotation[1], ['inject', 'persistent'], true))
				{
					$attributeArray[] = Strings::firstUpper($annotation[1]);

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

			if(isset($path) && !Strings::contains($content, 'use ' . $path))
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
}
