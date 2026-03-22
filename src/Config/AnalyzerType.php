<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Analyzer type enumeration.
 */
enum AnalyzerType: string
{
    case PhpStan = 'phpstan';
    case Debug = 'debug';
}
