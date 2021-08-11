<?php

declare(strict_types=1);

namespace Nette\CodingStandard\Fixer\ClassNotation;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Preg;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class Php8ClassPropertyFixer extends AbstractFixer
{
	/**
	 * {@inheritdoc}
	 */
	public function getDefinition()
	{
		return new FixerDefinition(
			'Class properties should be written in PHP8 format.',
			[
				[new CodeSample('<?php
class ValidPresenter extends Presenter
{
	/**
	 * @var string
	 * @persistent
	 */
	public $property;

	/**
	 * @var \My\Model
	 * @inject
	 */
	public $model;

	/**
	 * @var Explorer 
	 * @inject
	 * @persistent
	 */
	public $database;
}
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
		return $tokens->isAnyTokenKindsFound(Token::getClassyTokenKinds());
	}

	/**
	 * {@inheritdoc}
	 */
	protected function applyFix(\SplFileInfo $file, Tokens $tokens)
	{
		$content = $tokens->generateCode();

		$pattern = '/\/\*\*((?:\R[ \t]*\*[ \t]*@(?:inject|persistent|var)[ \t\S]*)+)\R[ \t]*\*\/\R[ \t]*(\w+) (\$\w+;)/';

		Preg::matchAll($pattern, $content, $matches, PREG_SET_ORDER);

		foreach($matches as $match)
		{
			$dataType = '';
			$attributeArray = [];

			$annotationArray = explode("\t", trim(str_replace('*', '', $match[1])));

			foreach($annotationArray as $annotation)
			{
				$wordArray = explode(' ', $annotation);

				if($wordArray[0] === '@var' && isset($wordArray[1]))
				{
					$dataType = $wordArray[1];
				}
				elseif($wordArray[0] === '@inject')
				{
					$attributeArray[] = 'Inject';
				}
				elseif($wordArray[0] === '@persistent')
				{
					$attributeArray[] = 'Persistent';
				}
			}

			$output = '';

			if($attributeArray)
			{
				$output .= '#[' . implode(', ', $attributeArray) . "]\n";
			}

			$output .= $match[2] . ' ';
			$output .= $dataType ? $dataType . ' ' : null;
			$output .= $match[3];

			$content = str_replace($match[0], $output, $content);
			file_put_contents('bs.txt', $content);
		}

		$newTokens = Tokens::fromCode($content);

		foreach($newTokens as $index => $token)
		{
			$newTokens[$index] = new Token($token->getContent());
		}

		$tokens->overrideRange(0, $tokens->count() - 1, $newTokens);
	}
}
