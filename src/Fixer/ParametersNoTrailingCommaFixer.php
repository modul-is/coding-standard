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

final class ParametersNoTrailingCommaFixer extends AbstractFixer
{
	/**
	 * {@inheritdoc}
	 */
	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'Parameters should not have a trailing comma.',
			[
				[new CodeSample('<?php
public function __construct
(
	public array $listOfEmotions,
	array $listOfSkills,
	array $listOfElements,
)
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

		$string = '/(,)(\s*\))/';

		Preg::matchAll($string, $content, $matches, PREG_SET_ORDER);

		foreach($matches as $match)
		{
			if(!empty($match[1]) && !empty($match[2]))
			{
				$newContent = preg_replace($string, $match[2], $content);

				$newTokens = Tokens::fromCode($newContent);

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
