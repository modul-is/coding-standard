<?php

declare(strict_types=1);

namespace ModulIS\Fixer\Whitespace;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class ConstructWhitespaceFixer extends AbstractFixer
{
	/**
	 * {@inheritdoc}
	 */
	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'There must be correct whitespace in constructors.',
			[
				[
					new CodeSample('<?php
public function __construct(
	public string $a,
){
	$this->a = $a;
}
'					)
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

		$string = '/(\t+)(public function __construct)(\s*)(\()(\s*)([^\)]*)\s*(\))(\s*)({[^}]*})/';

		preg_match($string, $content, $matches);

		if(!empty($matches[2]) && !empty($matches[6]) && (!str_contains($matches[3], PHP_EOL) || !str_contains($matches[5], PHP_EOL) || !str_contains($matches[8], PHP_EOL)))
		{
			$newContent = preg_replace($string, '$1$2' . PHP_EOL . '$1$4' . PHP_EOL . "$1\t$6$7" . PHP_EOL . '$1$9', $content);

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
