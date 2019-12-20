<?php

declare(strict_types=1);

namespace Nette\CodingStandard\Fixer\ReturnNotation;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Preg;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class FactoryReturnTypeFixer extends AbstractFixer
{
	/**
	 * {@inheritdoc}
	 */
	public function getDefinition()
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
	 */
	public function isCandidate(Tokens $tokens)
	{
		return $tokens->isTokenKindFound(T_INTERFACE);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function applyFix(\SplFileInfo $file, Tokens $tokens)
	{
		$content = $tokens->generateCode();

		$string = '/(interface I(\w+)Factory\s*{.*function create\(\))(.*);/s';

		Preg::match($string, $content, $matches);

		if(empty($matches[3]))
		{
			$newString = $matches[1] . ': ' . $matches[2] . ';';

			$newContent = preg_replace($string, $newString, $content);

			$newTokens = Tokens::fromCode($newContent);

			foreach ($newTokens as $index => $token)
			{
				$newTokens[$index] = new Token($token->getContent());
			}

			$tokens->overrideRange(0, $tokens->count() - 1, $newTokens);
		}
	}
}
