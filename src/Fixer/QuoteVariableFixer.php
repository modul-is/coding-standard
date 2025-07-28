<?php

declare(strict_types=1);

namespace ModulIS\Fixer\Basic;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class QuoteVariableFixer extends AbstractFixer
{
	/**
	 * {@inheritdoc}
	 */
	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'There must be no unescaped variables in double quotes.',
			[
				[new CodeSample('<?php
$a = "Metoda $string neexistuje";
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

		$newContent = $this->convertInterpolatedString($content);

		$newTokens = Tokens::fromCode($newContent);

		foreach($newTokens as $index => $token)
		{
			$newTokens[$index] = new Token($token->getContent());
		}

		$tokens->overrideRange(0, $tokens->count() - 1, $newTokens);
	}


	private function convertInterpolatedString(string $content): string
	{
		$newContent = preg_replace_callback('/"((?:[^"\'\.]*\$[\w]+[^"\'\.]*)+)"/', function($matches)
		{
			preg_match_all('/(\$[\w]+)|([^\$]+)/', $matches[1], $parts);

			$segments = [];

			foreach($parts[0] as $piece)
			{
				if(preg_match('/^\$[\w]+$/', $piece))
				{
					$segments[] = $piece;
				}
				else
				{
					$segments[] = "'" . $piece . "'";
				}
			}

			return implode(' . ', $segments);
		}, $content);

		return $newContent ?: $content;
	}


	public function getName(): string
	{
		return 'ModulIS/' . parent::getName();
	}
}
