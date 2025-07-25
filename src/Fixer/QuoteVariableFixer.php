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

		$string = '/"([^\';]*)(\$[\w]+)([^\';]*)"/';

		if(preg_match($string, $content, $matches))
		{
			$newContent = preg_replace($string, '"$1\' . $2 . \'$3"', $content);

			while(preg_match($string, $newContent, $matches))
			{
				$newContent = preg_replace($string, '"$1\' . $2 . \'$3"', $newContent);
			}

			$a = str_replace('"', '\'', $matches[0]);
			$newContent = str_replace($matches[0], $a, $newContent);
		}
/*$f=fopen('bs.txt', 'a+');
fwrite($f, $newContent);
fclose($f);*/
		$newTokens = Tokens::fromCode($newContent ?? $content);

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
