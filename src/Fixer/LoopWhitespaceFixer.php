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
			'There must be correct whitespace in loops and conditions.',
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

		// Add newline after }
		$newContent = preg_replace('/^([ \t]*)}\s*(?=(elseif|else)\b)/mu', '$1}' . PHP_EOL . '$1', $content);

		// Add newline before {
		$newContent = preg_replace(
			'~^([ \t]*)(if|elseif|else|for|foreach|while|switch)(\s*\((?>[^()]+|\([^()]*\))*\))?[ \t]*(?!\n)\{~mxu',
			'$1$2$3' . PHP_EOL . '$1{',
			$newContent
		);

		// Remove space before parentheses
		$newContent = preg_replace('/\b(if|elseif|for|foreach|while|switch)\s+\(/u', '$1(', $newContent);

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
