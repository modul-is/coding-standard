<?php

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Nette\CodingStandard\Fixer\Basic;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurationDefinitionFixerInterface;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * Fixer for rules defined in PSR2 ¶4.1, ¶4.4, ¶5.
 *
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 */
final class BracesFixer extends AbstractFixer implements ConfigurationDefinitionFixerInterface, WhitespacesAwareFixerInterface
{
	/**
	 * @internal
	 */
	private const LINE_NEXT = 'next';

	/**
	 * @internal
	 */
	private const LINE_SAME = 'same';


	/**
	 * {@inheritdoc}
	 */
	public function getDefinition()
	{
		return new FixerDefinition(
			'The body of each structure MUST be enclosed by braces. Braces should be properly placed. Body of braces should be properly indented.',
			[
				new CodeSample(
'<?php

class Foo {
	public function bar($baz) {
		if ($baz = 900) echo "Hello!";

		if ($baz = 9000)
			echo "Wait!";

		if ($baz == true)
		{
			echo "Why?";
		}
		else
		{
			echo "Ha?";
		}

		if (is_array($baz))
			foreach ($baz as $b)
			{
				echo $b;
			}
	}
}
'
				),
				new CodeSample(
'<?php
$positive = function ($item) { return $item >= 0; };
$negative = function ($item) {
				return $item < 0; };
',
					['allow_single_line_closure' => true]
				),
				new CodeSample(
'<?php

class Foo
{
	public function bar($baz)
	{
		if ($baz = 900) echo "Hello!";

		if ($baz = 9000)
			echo "Wait!";

		if ($baz == true)
		{
			echo "Why?";
		}
		else
		{
			echo "Ha?";
		}

		if (is_array($baz))
			foreach ($baz as $b)
			{
				echo $b;
			}
	}
}
',
					['position_after_functions_and_oop_constructs' => self::LINE_SAME]
				),
			]
		);
	}


	/**
	 * {@inheritdoc}
	 */
	public function getPriority()
	{
		// should be run after the ElseIfFixer, NoEmptyStatementFixer and NoUselessElseFixer
		return -25;
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
		$this->fixMissingControlBraces($tokens);
		$this->fixControlContinuationBraces($tokens);
		$this->fixSpaceAroundToken($tokens);
	}


	/**
	 * {@inheritdoc}
	 */
	protected function createConfigurationDefinition()
	{
		return new FixerConfigurationResolver([
			(new FixerOptionBuilder('allow_single_line_closure', 'Whether single line lambda notation should be allowed.'))
				->setAllowedTypes(['bool'])
				->setDefault(false)
				->getOption(),
			(new FixerOptionBuilder('position_after_functions_and_oop_constructs', 'whether the opening brace should be placed on "next" or "same" line after classy constructs (non-anonymous classes, interfaces, traits, methods and non-lambda functions).'))
				->setAllowedValues([self::LINE_NEXT, self::LINE_SAME])
				->setDefault(self::LINE_NEXT)
				->getOption(),
		]);
	}


	private function fixControlContinuationBraces(Tokens $tokens)
	{
		$controlContinuationTokens = $this->getControlContinuationTokens();

		for ($index = count($tokens) - 1; 0 <= $index; --$index)
		{
			$token = $tokens[$index];

			if (!$token->isGivenKind($controlContinuationTokens))
			{
				continue;
			}

			$prevIndex = $tokens->getPrevNonWhitespace($index);
			$prevToken = $tokens[$prevIndex];

			if (!$prevToken->equals('}'))
			{
				continue;
			}

			$tokens->ensureWhitespaceAtIndex($index - 1, 1, ' ');
		}
	}


	private function fixMissingControlBraces(Tokens $tokens)
	{
		$controlTokens = $this->getControlTokens();

		for ($index = $tokens->count() - 1; 0 <= $index; --$index)
		{
			$token = $tokens[$index];

			if (!$token->isGivenKind($controlTokens))
			{
				continue;
			}

			$parenthesisEndIndex = $this->findParenthesisEnd($tokens, $index);
			$tokenAfterParenthesis = $tokens[$tokens->getNextMeaningfulToken($parenthesisEndIndex)];

			// if Token after parenthesis is { then we do not need to insert brace, but to fix whitespace before it
			if ($tokenAfterParenthesis->equals('{'))
			{
				$tokens->ensureWhitespaceAtIndex($parenthesisEndIndex + 1, 0, ' ');

				continue;
			}

			// do not add braces for cases:
			// - structure without block, e.g. while ($iter->next());
			// - structure with block, e.g. while ($i) {...}, while ($i) : {...} endwhile;
			if ($tokenAfterParenthesis->equalsAny([';', '{', ':']))
			{
				continue;
			}

			$statementEndIndex = $this->findStatementEnd($tokens, $parenthesisEndIndex);

			// insert closing brace
			$tokens->insertAt($statementEndIndex + 1, [new Token([T_WHITESPACE, ' ']), new Token('}')]);

			// insert missing `;` if needed
			if (!$tokens[$statementEndIndex]->equalsAny([';', '}']))
			{
				$tokens->insertAt($statementEndIndex + 1, new Token(';'));
			}

			// insert opening brace
			$tokens->insertAt($parenthesisEndIndex + 1, new Token('{'));
			$tokens->ensureWhitespaceAtIndex($parenthesisEndIndex + 1, 0, ' ');
		}
	}


	private function fixSpaceAroundToken(Tokens $tokens)
	{
		$controlTokens = $this->getControlTokens();
		$controlContinuationTokens = $this->getControlContinuationTokens();

		for($index = $tokens->count() - 1; 0 <= $index; --$index)
		{
			$token = $tokens[$index];
			$prevNonWhitespaceIndex = $tokens->getPrevNonWhitespace($index);
			$nextNonWhitespaceIndex = $tokens->getNextNonWhitespace($index);

			// Declare tokens don't follow the same rules as other control statements
			if($token->isGivenKind(T_DECLARE))
			{
				$this->fixDeclareStatement($tokens, $index);
			}
			elseif($token->isGivenKind($controlTokens) || $token->isGivenKind(CT::T_USE_LAMBDA))
			{
				$braceStartIndex = $tokens->getNextTokenOfKind($index, ['{']);
				$nestWhitespace = $tokens[$braceStartIndex + 1]->getContent();

				if($token->isGivenKind($controlContinuationTokens) && $tokens[$index - 1]->isWhitespace() && !$tokens[$prevNonWhitespaceIndex]->equals(';'))
				{
					$tokens->ensureWhitespaceAtIndex($index - 1, 1, mb_substr($nestWhitespace, 0, -1));
				}

				if($tokens[$nextNonWhitespaceIndex]->equals('('))
				{
					if($tokens[$index + 1]->isWhitespace(" "))
					{
						$tokens->ensureWhitespaceAtIndex($index + 1, 0, '');
					}

					$parenthesisEndIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $nextNonWhitespaceIndex);

					if($tokens[$parenthesisEndIndex + 1]->isWhitespace(" "))
					{
						$tokens->ensureWhitespaceAtIndex($parenthesisEndIndex + 1, 0, mb_substr($nestWhitespace, 0, -1));
					}
				}
				else
				{
					$tokens->ensureWhitespaceAtIndex($index + 1, 1, mb_substr($nestWhitespace, 0, -1));
				}
			}
		}
	}


	/**
	 * @param Tokens $tokens
	 * @param int    $structureTokenIndex
	 *
	 * @return int
	 */
	private function findParenthesisEnd(Tokens $tokens, $structureTokenIndex)
	{
		$nextIndex = $tokens->getNextMeaningfulToken($structureTokenIndex);
		$nextToken = $tokens[$nextIndex];

		// return if next token is not opening parenthesis
		if (!$nextToken->equals('('))
		{
			return $structureTokenIndex;
		}

		return $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $nextIndex);
	}


	private function findStatementEnd(Tokens $tokens, $parenthesisEndIndex)
	{
		$nextIndex = $tokens->getNextMeaningfulToken($parenthesisEndIndex);
		$nextToken = $tokens[$nextIndex];

		if (!$nextToken)
		{
			return $parenthesisEndIndex;
		}

		if ($nextToken->equals('{'))
		{
			return $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $nextIndex);
		}

		if ($nextToken->isGivenKind($this->getControlTokens()))
		{
			$parenthesisEndIndex = $this->findParenthesisEnd($tokens, $nextIndex);

			$endIndex = $this->findStatementEnd($tokens, $parenthesisEndIndex);

			if ($nextToken->isGivenKind([T_IF, T_TRY, T_DO]))
			{
				$openingTokenKind = $nextToken->getId();

				while (true)
                {
					$nextIndex = $tokens->getNextMeaningfulToken($endIndex);
					$nextToken = isset($nextIndex) ? $tokens[$nextIndex] : null;
					if ($nextToken && $nextToken->isGivenKind($this->getControlContinuationTokensForOpeningToken($openingTokenKind)))
					{
						$parenthesisEndIndex = $this->findParenthesisEnd($tokens, $nextIndex);

						$endIndex = $this->findStatementEnd($tokens, $parenthesisEndIndex);

						if ($nextToken->isGivenKind($this->getFinalControlContinuationTokensForOpeningToken($openingTokenKind)))
						{
							return $endIndex;
						}
					}
					else
					{
						break;
					}
				}
			}

			return $endIndex;
		}

		$index = $parenthesisEndIndex;

		while (true)
        {
			$token = $tokens[++$index];

			// if there is some block in statement (eg lambda function) we need to skip it
			if ($token->equals('{'))
			{
				$index = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $index);
				continue;
			}

			if ($token->equals(';'))
			{
				return $index;
			}

			if ($token->isGivenKind(T_CLOSE_TAG))
			{
				return $tokens->getPrevNonWhitespace($index);
			}
		}

		throw new \RuntimeException('Statement end not found.');
	}


	private function getControlTokens()
	{
		static $tokens = [
			T_DECLARE,
			T_DO,
			T_ELSE,
			T_ELSEIF,
			T_FINALLY,
			T_FOR,
			T_FOREACH,
			T_IF,
			T_WHILE,
			T_TRY,
			T_CATCH,
			T_SWITCH,
		];

		return $tokens;
	}


	private function getControlContinuationTokens()
	{
		static $tokens = [
			T_CATCH,
			T_ELSE,
			T_ELSEIF,
			T_FINALLY,
		];

		return $tokens;
	}


	private function getControlContinuationTokensForOpeningToken($openingTokenKind)
	{
		if ($openingTokenKind === T_IF)
		{
			return [
				T_ELSE,
				T_ELSEIF,
			];
		}

		if ($openingTokenKind === T_DO)
		{
			return [T_WHILE];
		}

		if ($openingTokenKind === T_TRY)
		{
			return [
				T_CATCH,
				T_FINALLY,
			];
		}

		return [];
	}


	private function getFinalControlContinuationTokensForOpeningToken($openingTokenKind)
	{
		if ($openingTokenKind === T_IF)
		{
			return [T_ELSE];
		}

		if ($openingTokenKind === T_TRY)
		{
			return [T_FINALLY];
		}

		return [];
	}


	/**
	 * @param Tokens $tokens
	 * @param int    $index
	 */
	private function fixDeclareStatement(Tokens $tokens, $index)
	{
		$tokens->removeTrailingWhitespace($index);

		$startParenthesisIndex = $tokens->getNextTokenOfKind($index, ['(']);
		$tokens->removeTrailingWhitespace($startParenthesisIndex);

		$endParenthesisIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $startParenthesisIndex);
		$tokens->removeLeadingWhitespace($endParenthesisIndex);

		$startBraceIndex = $tokens->getNextTokenOfKind($endParenthesisIndex, [';', '{']);
		$startBraceToken = $tokens[$startBraceIndex];

		if ($startBraceToken->equals('{'))
		{
			$this->fixSingleLineWhitespaceForDeclare($tokens, $startBraceIndex);
		}
	}


	/**
	 * @param Tokens $tokens
	 * @param int    $startBraceIndex
	 */
	private function fixSingleLineWhitespaceForDeclare(Tokens $tokens, $startBraceIndex)
	{
		// fix single-line whitespace before {
		// eg: `declare(ticks=1){` => `declare(ticks=1) {`
		// eg: `declare(ticks=1)   {` => `declare(ticks=1) {`
		if(!$tokens[$startBraceIndex - 1]->isWhitespace() || $tokens[$startBraceIndex - 1]->isWhitespace(" \t"))
		{
			$tokens->ensureWhitespaceAtIndex($startBraceIndex - 1, 1, ' ');
		}
	}


	/**
	 * @param Tokens $tokens
	 * @param int    $startParenthesisIndex
	 * @param int    $endParenthesisIndex
	 *
	 * @return bool
	 */
	private function isMultilined(Tokens $tokens, $startParenthesisIndex, $endParenthesisIndex)
	{
		for ($i = $startParenthesisIndex; $i < $endParenthesisIndex; ++$i)
		{
			if (strpos($tokens[$i]->getContent(), "\n") !== false)
			{
				return true;
			}
		}

		return false;
	}
}
