<?php

declare(strict_types=1);


return function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $containerConfigurator): void {
	$services = $containerConfigurator->services();

	// Convert `heredoc` to `nowdoc` where possible.
	$services->set(PhpCsFixer\Fixer\StringNotation\HeredocToNowdocFixer::class);

	//$services->set(PhpCsFixer\Fixer\Basic\NonPrintableCharacterFixer::class)
	//->call('configure', [['use_escape_sequences_in_strings' => true]]);
};
