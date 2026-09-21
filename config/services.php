<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\Action\Inspect\InspectAction;
use App\Action\Query\QueryAction;
use App\Command\GraphCommand;
use App\Command\InspectCommand;
use App\Reporter\Query\QueryReporterFactory;
use App\Reporter\ReporterFactory;

return function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public()
    ;

    $services->set(InspectAction::class);
    $services->set(ReporterFactory::class);
    $services->set(InspectCommand::class)
        ->tag('console.command')
    ;

    $services->set(QueryAction::class);
    $services->set(QueryReporterFactory::class);
    $services->set(GraphCommand::class)
        ->tag('console.command')
    ;
};
