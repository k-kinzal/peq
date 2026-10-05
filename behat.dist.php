<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Behat\PHPUnitAssertionsExtension\BehatPHPUnitAssertionsExtension;
use Behat\PHPUnitAssertionsExtension\PHPUnitExceptionStringer;
use Spec\Context\ConformanceContext;
use Spec\Context\ProgramContext;

// Version 1.0.0 of the extension still registers Behat 3's exception stringer name.
// Keep its moved implementation available under that name until upstream fixes it.
if (!class_exists('Behat\Testwork\Exception\Stringer\PHPUnitExceptionStringer')) {
    class_alias(PHPUnitExceptionStringer::class, 'Behat\Testwork\Exception\Stringer\PHPUnitExceptionStringer');
}

return (new Config())
    ->withProfile(
        (new Profile('default'))
            ->withExtension(new Extension(BehatPHPUnitAssertionsExtension::class))
            ->withSuite(
                (new Suite('gql'))
                    ->withPaths('%paths.base%/spec/features')
                    ->withContexts(ProgramContext::class, ConformanceContext::class),
            ),
    )
;
