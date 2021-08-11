<?php

declare(strict_types=1);

namespace Nette\CodingStandard\Fixer\Basic;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class DumpFixer extends AbstractFixer
{
	/**
	 * {@inheritdoc}
	 */
	public function getDefinition()
	{
		return new FixerDefinition(
			'There must be no die or dump in code.',
			[
				[new CodeSample('<?php
var_dump(1);
bdump(2);
die;
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

		$string = '/(?:\S*dump|die)(?:\(.*\))*;\s*/';

		$newContent = preg_replace($string, '', $content);

		$newTokens = Tokens::fromCode($newContent);

		foreach($newTokens as $index => $token)
		{
			$newTokens[$index] = new Token($token->getContent());
		}

		$tokens->overrideRange(0, $tokens->count() - 1, $newTokens);
	}
}
