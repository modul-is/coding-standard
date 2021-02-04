<?php

declare(strict_types=1);


return function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $containerConfigurator): void {
	$services = $containerConfigurator->services();

	// Functions should be used with `$strict` param set to `true`
	$services->set(PhpCsFixer\Fixer\Strict\StrictParamFixer::class);
};
