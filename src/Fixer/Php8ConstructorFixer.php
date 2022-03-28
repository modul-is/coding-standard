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

final class Php8ConstructorFixer extends AbstractFixer
{
	/**
	 * {@inheritdoc}
	 */
	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'Constructors should be written in PHP8 format if possible.',
			[
				[new CodeSample('<?php
protected string $property;

public function __construct
(
	string $property
)
{
	$this->property = $property;
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

		$propertyPattern = '/(#\[[\w\\\\]+\])?\s*((?:public|protected|private) )((?:[\w\\\\?|]+) )?\$(\w+);/';
		$constructorPattern = '/public function __construct\s*\(([\w\s$,\\\\]*)\)\s*{([\w\s\->$=;]*)}/';

		$propertyArray = [];

		Preg::matchAll($propertyPattern, $content, $matches, PREG_SET_ORDER);

		foreach($matches as $match)
		{
			if(!$match[1] && $match[2] && $match[3])
			{
				$propertyArray[$match[4]] = [
					'match' => $match[0],
					'scope' => $match[2],
					'type' => $match[3]
				];
			}
		}

		if($propertyArray)
		{
			Preg::match($constructorPattern, $content, $constructorMatches);

			if(isset($constructorMatches[1], $constructorMatches[2]))
			{
				$paramArray = array_map('trim', explode(',', $constructorMatches[1]));
				$initArray = array_map('trim', explode(';', $constructorMatches[2]));

				foreach($propertyArray as $name => $data)
				{
					if(in_array($data['type'] . '$' . $name, $paramArray, true) && in_array('$this->' . $name . ' = $' . $name, $initArray, true))
					{
						$content = str_replace($data['match'], '', $content);
						$content = str_replace($data['type'] . '$' . $name, $data['scope'] . $data['type'] . '$' . $name, $content);
						$content = str_replace("\t\t" . '$this->' . $name . ' = $' . $name . ';' . PHP_EOL, '', $content);
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
	}


	public function getName(): string
	{
		return 'ModulIS/' . parent::getName();
	}
}
