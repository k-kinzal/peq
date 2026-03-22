<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\Action\Inspect\InspectAction;
use App\Command\InspectCommand;

return function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public()
    ;

    $services->set(InspectAction::class);
    $services->set(InspectCommand::class)
        ->tag('console.command')
    ;
};
