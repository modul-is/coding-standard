<?php

declare(strict_types=1);


return function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $containerConfigurator): void {
	$services = $containerConfigurator->services();

	// array spacing
	$services->set(PhpCsFixer\Fixer\ArrayNotation\NoWhitespaceBeforeCommaInArrayFixer::class);
	$services->set(PhpCsFixer\Fixer\Whitespace\ArrayIndentationFixer::class);
	$services->set(PhpCsFixer\Fixer\ArrayNotation\TrimArraySpacesFixer::class);
	$services->set(PhpCsFixer\Fixer\ArrayNotation\WhitespaceAfterCommaInArrayFixer::class);

	// commas
	$services->set(Nette\CodingStandard\Fixer\ArrayNotation\NoTrailingCommaInMultilineArrayFixer::class);
	$services->set(PhpCsFixer\Fixer\ArrayNotation\NoTrailingCommaInSinglelineArrayFixer::class);

	$services->set(PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer::class)
		->call('configure', [[
			'syntax' => 'short',
		]]);

	// $arr{} to $arr[]
	$services->set(PhpCsFixer\Fixer\ArrayNotation\NormalizeIndexBraceFixer::class);
};
