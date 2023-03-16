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

final class Php8EntityPropertyFixer extends AbstractFixer
{
	/**
	 * {@inheritdoc}
	 */
	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'Entity properties should be written in PHP8 format.',
			[
				[new CodeSample('<?php
/**
 * @property-read int $id
 * @property int|null $number
 * @property string $text
 * @property string|null $other_text
 * @property json $data
 */
class ValidEntity extends \ModulIS\Entity
{
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

		$propertyPattern = '/\/\*\*(?:\R[ \t]*\*[ \t]*@(?:property(?:-read)?)[ \t\S]*)+\R[ \t]*\*\/\R/';
		$annotationPattern = '/@(property(?:-read)?)(?: (.+) (\$.+))?/';
		$classPattern = '/(class .+\s*{)(\s*)(.)/';

		if(!Preg::match($propertyPattern, $content))
		{
			return;
		}

		$annotationArray = [];

		Preg::matchAll($annotationPattern, $content, $matches, PREG_SET_ORDER);

		foreach($matches as $match)
		{
			if(isset($match[1], $match[2], $match[3]))
			{
				if(strpos($match[2], 'json') !== false)
				{
					$match[2] = strpos($match[2], 'null') !== false ? 'array|null' : 'array';
				}
				elseif(strpos($match[2], 'double') !== false)
				{
					$match[2] = strpos($match[2], 'null') !== false ? 'float|null' : 'float';
				}
				elseif(strpos($match[2], 'date') !== false)
				{
					$match[2] = strpos($match[2], 'null') !== false ? '\Nette\Utils\DateTime|null' : '\Nette\Utils\DateTime';
				}

				$annotationArray[] = ($match[1] === 'property-read' ? '#[\ModulIS\Attribute\ReadonlyProperty]' . PHP_EOL . "\t" : null) . 'public ' . $match[2] . ' ' . trim($match[3]) . ';';
			}
		}

		if(!$annotationArray)
		{
			return;
		}

		Preg::match($classPattern, $content, $class);

		if(!isset($class[1], $class[2], $class[3]))
		{
			return;
		}

		$content = Preg::replace($classPattern, $class[1] . PHP_EOL . "\t" . implode(PHP_EOL . PHP_EOL . "\t", $annotationArray) . ($class[3] === '}' ? PHP_EOL : PHP_EOL . PHP_EOL . "\t") . $class[3], $content);
		$content = Preg::replace($propertyPattern, '', $content);

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
