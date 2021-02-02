<?php

declare(strict_types=1);

return function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $containerConfigurator): void {
	$containerConfigurator->import(__DIR__ . '/php71.php');

	$services = $containerConfigurator->services();

	$services->set(PhpCsFixer\Fixer\Whitespace\HeredocIndentationFixer::class);
};
