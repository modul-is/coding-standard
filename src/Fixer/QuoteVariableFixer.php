<?php

declare(strict_types=1);

namespace ModulIS\Fixer\Basic;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\CT;
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
		return $tokens->isTokenKindFound('"');
	}


	/**
	 * {@inheritdoc}
	 */
	protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
	{
		do
		{
			$changed = $this->convertStrings($tokens);
		}
		while($changed);
	}


	private function convertStrings(Tokens $tokens): bool
	{
		$strings = [];

		for($index = 0, $count = $tokens->count(); $index < $count; $index++)
		{
			if($tokens[$index]->equals('"'))
			{
				$end = $this->findClosingQuote($tokens, $index);

				$strings[] = [$index, $end];

				$index = $end;
			}
		}

		$changed = false;

		foreach(array_reverse($strings) as [$start, $end])
		{
			$newTokens = $this->convertInterpolatedString($tokens, $start, $end);

			if($newTokens !== null)
			{
				$tokens->overrideRange($start, $end, $newTokens);

				$changed = true;
			}
		}

		return $changed;
	}


	private function findClosingQuote(Tokens $tokens, int $start): int
	{
		$depth = 0;

		for($index = $start + 1, $count = $tokens->count(); $index < $count; $index++)
		{
			$token = $tokens[$index];

			if($token->isGivenKind([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES]))
			{
				$depth++;
			}
			elseif($token->isGivenKind([CT::T_CURLY_CLOSE, CT::T_DOLLAR_CLOSE_CURLY_BRACES]))
			{
				$depth--;
			}
			elseif($depth === 0 && $token->equals('"'))
			{
				return $index;
			}
		}

		throw new \LogicException('Closing quote of interpolated string not found.');
	}


	private function findCurlyClose(Tokens $tokens, int $start): int
	{
		$depth = 0;

		for($index = $start, $count = $tokens->count(); $index < $count; $index++)
		{
			if($tokens[$index]->isGivenKind(T_CURLY_OPEN))
			{
				$depth++;
			}
			elseif($tokens[$index]->isGivenKind(CT::T_CURLY_CLOSE) && --$depth === 0)
			{
				return $index;
			}
		}

		throw new \LogicException('Closing curly brace of interpolated string not found.');
	}


	private function sliceTokens(Tokens $tokens, int $from, int $to): array
	{
		$slice = [];

		for($index = $from; $index <= $to; $index++)
		{
			$slice[] = $tokens[$index];
		}

		return $slice;
	}


	private function convertArrayKey(array $key): ?array
	{
		if(count($key) === 1 && $key[0]->isGivenKind(T_VARIABLE))
		{
			return $key;
		}

		if(count($key) === 1 && $key[0]->isGivenKind(T_STRING))
		{
			return [new Token([T_CONSTANT_ENCAPSED_STRING, "'" . $key[0]->getContent() . "'"])];
		}

		$number = implode('', array_map(fn(Token $token) => $token->getContent(), $key));

		if(!preg_match('/^-?\d+$/', $number))
		{
			return null;
		}

		if((string) (int) $number !== $number)
		{
			return [new Token([T_CONSTANT_ENCAPSED_STRING, "'" . $number . "'"])];
		}

		return str_starts_with($number, '-')
			? [new Token('-'), new Token([T_LNUMBER, substr($number, 1)])]
			: [new Token([T_LNUMBER, $number])];
	}


	private function convertInterpolatedString(Tokens $tokens, int $start, int $end): ?array
	{
		$segments = [];

		for($index = $start + 1; $index < $end; $index++)
		{
			$token = $tokens[$index];

			if($token->isGivenKind(T_ENCAPSED_AND_WHITESPACE))
			{
				$content = $token->getContent();

				$literal = $this->convertLiteral($content) ?? '"' . $content . '"';

				$segments[] = [new Token([T_CONSTANT_ENCAPSED_STRING, $literal])];
			}
			elseif($token->isGivenKind(T_VARIABLE))
			{
				$segment = [$token];

				if($tokens[$index + 1]->isGivenKind([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR]) && $tokens[$index + 2]->isGivenKind(T_STRING))
				{
					$segment[] = $tokens[$index + 1];
					$segment[] = $tokens[$index + 2];

					$index += 2;
				}
				elseif($tokens[$index + 1]->equals('['))
				{
					$close = $tokens->getNextTokenOfKind($index + 1, [']']);
					$key = $this->convertArrayKey($this->sliceTokens($tokens, $index + 2, $close - 1));

					if($key === null)
					{
						return null;
					}

					array_push($segment, $tokens[$index + 1], ...$key);
					$segment[] = $tokens[$close];

					$index = $close;
				}

				$segments[] = $segment;
			}
			elseif($token->isGivenKind(T_CURLY_OPEN))
			{
				$close = $this->findCurlyClose($tokens, $index);

				$segments[] = $this->sliceTokens($tokens, $index + 1, $close - 1);

				$index = $close;
			}
			else
			{
				return null;
			}
		}

		if(count($segments) === 1 && $segments[0][0]->isGivenKind(T_VARIABLE))
		{
			return [new Token([T_STRING_CAST, '(string)']), new Token([T_WHITESPACE, ' ']), ...$segments[0]];
		}

		$newTokens = [];

		foreach($segments as $i => $segment)
		{
			if($i > 0)
			{
				array_push($newTokens, new Token([T_WHITESPACE, ' ']), new Token('.'), new Token([T_WHITESPACE, ' ']));
			}

			array_push($newTokens, ...$segment);
		}

		return $newTokens;
	}


	private function convertLiteral(string $content): ?string
	{
		if(str_contains($content, "'"))
		{
			return null;
		}

		$convertible = true;

		$value = preg_replace_callback('/\\\\(.)/s', function($matches) use (&$convertible)
		{
			if(in_array($matches[1], ['\\', '$', '"'], true))
			{
				return $matches[1];
			}

			// \n, \t, \x41, \u{41}, \101... have no equivalent in single quotes
			if(preg_match('/[nrtvef0-7xu]/', $matches[1]))
			{
				$convertible = false;
			}

			return $matches[0];
		}, $content);

		if(!$convertible)
		{
			return null;
		}

		return "'" . preg_replace('/\\\\(?=\\\\|$)/', '\\\\\\\\', $value) . "'";
	}


	public function getName(): string
	{
		return 'ModulIS/' . parent::getName();
	}
}
