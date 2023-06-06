<?php

declare(strict_types=1);

namespace ModulIS\Fixer\Basic;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Preg;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class NamedArgumentSpaceFixer extends AbstractFixer
{
	/**
	 * {@inheritdoc}
	 */
	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'There must be a space between a named argument and its value.',
			[
				[new CodeSample('<?php
$this->method(argument:1);
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

		Preg::matchAll('/([\'\"]?)([\w -]*)(\w+:)([\w\.\'\"]+)/', $content, $matches, PREG_SET_ORDER);

		foreach($matches as $match)
		{
			if(isset($match[1], $match[2], $match[3], $match[4]) && !$match[1])
			{
				$newContent = str_replace($match[0], $match[2] . $match[3] . ' ' . $match[4], $content);
			}
		}

		if(isset($newContent))
		{
			$newTokens = Tokens::fromCode($newContent);

			foreach($newTokens as $index => $token)
			{
				$newTokens[$index] = new Token($token->getContent());
			}

			$tokens->overrideRange(0, $tokens->count() - 1, $newTokens);
		}
	}


	public function getName(): string
	{
		return 'ModulIS/' . parent::getName();
	}
}
