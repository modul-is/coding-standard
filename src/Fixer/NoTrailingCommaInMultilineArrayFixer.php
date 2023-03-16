<?php

declare(strict_types=1);

namespace ModulIS\Fixer\ArrayNotation;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\FixerDefinition\VersionSpecification;
use PhpCsFixer\FixerDefinition\VersionSpecificCodeSample;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\TokensAnalyzer;

final class NoTrailingCommaInMultilineArrayFixer extends AbstractFixer
{
	/**
	 * {@inheritdoc}
	 */
	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'PHP multi-line arrays should not have a trailing comma.',
			[
				new CodeSample("<?php\narray(\n    1,\n    2,\n);\n"),
				new VersionSpecificCodeSample(
					<<<'SAMPLE'
<?php
    $x = [
        'foo',
        <<<EOD
            bar,
            EOD
    ];

SAMPLE
					,
					new VersionSpecification(70300),
					['after_heredoc' => true]
				),
			]
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function isCandidate(Tokens $tokens): bool
	{
		return $tokens->isAnyTokenKindsFound([T_ARRAY, CT::T_ARRAY_SQUARE_BRACE_OPEN]);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
	{
		$tokensAnalyzer = new TokensAnalyzer($tokens);

		for ($index = $tokens->count() - 1; $index >= 0; --$index) {
			if ($tokensAnalyzer->isArray($index) && $tokensAnalyzer->isArrayMultiLine($index)) {
				$this->fixArray($tokens, $index);
			}
		}
	}

	private function fixArray(Tokens $tokens, $index): void
	{
		$startIndex = $index;

		if ($tokens[$startIndex]->isGivenKind(T_ARRAY))
		{
			$startIndex = $tokens->getNextTokenOfKind($startIndex, ['(']);
			$endIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $startIndex);
		} else
		{
			$endIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_ARRAY_SQUARE_BRACE, $startIndex);
		}

		$beforeEndIndex = $tokens->getPrevMeaningfulToken($endIndex);
		$beforeEndToken = $tokens[$beforeEndIndex];

		if ($beforeEndToken->equals(','))
		{
			$tokens->clearAt($beforeEndIndex);
		}
	}

	public function getName(): string
	{
		return 'ModulIS/' . parent::getName();
	}
}
