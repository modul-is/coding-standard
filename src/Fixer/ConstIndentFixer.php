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

final class ConstIndentFixer extends AbstractFixer
{
	/**
	 * {@inheritdoc}
	 */
	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'Indentation of constants must be consistent.',
			[
				[new CodeSample('<?php
protected const
	A = 1,
B = 2,
C = 3;
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

		$regex = '/(?:public|protected|private) const((\s+)[\S ]+,)/';

		Preg::matchAll($regex, $content, $matches, PREG_SET_ORDER);

		foreach($matches as $match)
		{
			$const = $match[1];

			if(isset($const) && !str_contains($const, '['))
			{
				$regex = '/(?:public|protected|private) const' . $const . '((\s+)([\S ]+[,;]))/';

				Preg::match($regex, $content, $nextMatches);

				while(isset($nextMatches[1]))
				{
					$content = preg_replace('/' . $nextMatches[1] . '/', $match[2] . $nextMatches[3], $content);

					if(str_ends_with($nextMatches[3], ';'))
					{
						break;
					}

					$const = $const . $match[2] . $nextMatches[3];
					$regex = '/(?:public|protected|private) const' . $const . '((\s+)([\S ]+[,;]))/';

					Preg::match($regex, $content, $nextMatches);
				}
			}
		}

		if($matches)
		{
			$newTokens = Tokens::fromCode($content);

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
