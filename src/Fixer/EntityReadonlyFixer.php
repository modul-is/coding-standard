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

final class EntityReadonlyFixer extends AbstractFixer
{
	/**
	 * {@inheritdoc}
	 */
	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'Entity Readonly attribute must be renamed to ReadonlyProperty for PHP 8.1 compatibility.',
			[
				[new CodeSample('<?php
#[\ModulIS\Attribute\Readonly]
public int $id;
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

		$pattern = '/#\[\\\\ModulIS\\\\Attribute\\\\Readonly\]/';

		if(!Preg::match($pattern, $content))
		{
			return;
		}

		$content = Preg::replace($pattern, '#[\ModulIS\Attribute\ReadonlyProperty]', $content);

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
