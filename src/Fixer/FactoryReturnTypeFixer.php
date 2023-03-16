<?php

declare(strict_types=1);

namespace ModulIS\Fixer\ReturnNotation;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Preg;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class FactoryReturnTypeFixer extends AbstractFixer
{
	/**
	 * {@inheritdoc}
	 */
	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'Component factory must have a set return type.',
			[
				[new CodeSample('<?php
interface IValidClassFactory
{
	function create();
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

		$string = '/(interface I?(\w+)Factory\s*{.*function create\(\))(.*);/s';

		Preg::match($string, $content, $matches);

		if(!empty($matches[1]) && !empty($matches[2]) && empty($matches[3]))
		{
			$newString = $matches[1] . ': ' . $matches[2] . ';';

			$newContent = preg_replace($string, $newString, $content);

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
