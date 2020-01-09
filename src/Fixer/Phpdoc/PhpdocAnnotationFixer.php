<?php

declare(strict_types=1);

namespace Nette\CodingStandard\Fixer\Phpdoc;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurationDefinitionFixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Preg;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class PhpdocAnnotationFixer extends AbstractFixer implements ConfigurationDefinitionFixerInterface
{
	/**
	 * {@inheritdoc}
	 */
	public function getDefinition()
	{
		return new FixerDefinition(
			'Some annotations should not be used anymore.',
			[
				[new CodeSample('<?php
/**
 * @todo Add foobar
 *
 * @param $id
 *
 * @return int
 *
 * @flash
 * @redirect
 * @redraw
 */
public function Foo($id) {}
')]
			]
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPriority()
	{
		// should be run before NoEmptyPhpdocFixer
		return 6;
	}

	/**
	 * {@inheritdoc}
	 */
	public function isCandidate(Tokens $tokens)
	{
		return $tokens->isTokenKindFound(T_DOC_COMMENT);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function createConfigurationDefinition()
	{
		return new FixerConfigurationResolver([
			(new FixerOptionBuilder('annotations', 'Which annotations to remove.'))
				->setAllowedTypes(['array'])
				->setDefault([])
				->getOption()
		]);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function applyFix(\SplFileInfo $file, Tokens $tokens)
	{
		foreach ($tokens as $index => $token)
		{
			if (!$token->isGivenKind(T_DOC_COMMENT))
			{
				continue;
			}

			$content = $token->getContent();
			$content = $this->fixOutdated($content);
			$tokens[$index] = new Token([T_DOC_COMMENT, $content]);
		}
	}

	private function fixOutdated($content)
	{
		$annotations = implode('|', $this->configuration['annotations']);

		return Preg::replace('((?:\R[ \t]*(?:\*[ \t]*@(?:' . $annotations . '))[ \t\S]*)+)', '', $content);
	}
}
