<?php

declare(strict_types=1);

namespace ModulIS\Fixer\Whitespace;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class LoopWhitespaceFixer extends AbstractFixer
{
	/**
	 * {@inheritdoc}
	 */
	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'There must be no die or dump in code.',
			[
				[
					new CodeSample('<?php
foreach ($array as $a)
{
	echo $a;
}
'
					),
					new CodeSample('<?php
if ($a)
{
	return $a;
} elseif ($b)
{
	return $b;
}
'
					)
				]
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

		$string = '/(while|foreach|for|switch|if|elseif|else)\s+(\()/';

		$newContent = preg_replace($string, '$1$2', $content);

		$newTokens = Tokens::fromCode($newContent);

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
