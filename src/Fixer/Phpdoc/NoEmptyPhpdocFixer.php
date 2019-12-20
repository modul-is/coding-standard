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

namespace Nette\CodingStandard\Fixer\Phpdoc;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Preg;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * @author SpacePossum
 */
final class NoEmptyPhpdocFixer extends AbstractFixer
{
    /**
     * {@inheritdoc}
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'There should not be empty PHPDoc blocks.',
            [new CodeSample("<?php /**  */\n")]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        // should be run before NoExtraBlankLinesFixer, NoTrailingWhitespaceFixer, NoWhitespaceInBlankLineFixer and
        // after PhpdocNoAccessFixer, PhpdocNoEmptyReturnFixer, PhpdocNoPackageFixer, PHPUnitTestAnnotationFixer.
        return 5;
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
    protected function applyFix(\SplFileInfo $file, Tokens $tokens)
    {
        foreach ($tokens as $index => $token)
        {
            if (!$token->isGivenKind(T_DOC_COMMENT))
            {
                continue;
            }

            if (Preg::match('#^/\*\*[\s\*]*\*/$#', $token->getContent()))
            {
                $tokens->clearTokenAndMergeSurroundingWhitespace($index);

                if($tokens[$index - 1]->isWhitespace())
                {
                    $nextIndex1 = $tokens->getNextMeaningfulToken($index);
                    $nextIndex2 = $tokens->getNextMeaningfulToken($nextIndex1);
                    $nextIndex3 = $tokens->getNextMeaningfulToken($nextIndex2);
                    $nextToken1 = $tokens[$nextIndex1];
                    $nextToken2 = $tokens[$nextIndex2];
                    $nextToken3 = $tokens[$nextIndex3];

                    $prevIndex = $tokens->getPrevMeaningfulToken($index);
                    $prevToken = $tokens[$prevIndex];

                    if($prevToken->equals('}') &&
                        (($nextToken1->isKeyword() && $nextToken2->isGivenKind(T_FUNCTION)) || ($nextToken1->isKeyword() && $nextToken2->isKeyword() && $nextToken3->isGivenKind(T_FUNCTION))))
                    {
                        $tokens->ensureWhitespaceAtIndex($index - 1, 0, "\n\n\n\t");
                    }
                    elseif($prevToken->equals(';'))
                    {
                        $tokens->ensureWhitespaceAtIndex($index - 1, 0, "\n\n\t");
                    }
                    elseif($prevToken->equals('{'))
                    {
                        $tokens->ensureWhitespaceAtIndex($index - 1, 0, "\n\t");
                    }
                }
            }
        }
    }
}
