<?php

declare(strict_types=1);

namespace Tests\App\Analyzer;

use App\Analyzer\DebugAnalyzer\DebugAnalyzer;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class DebugAnalyzerTest extends TestCase
{
    #[Test]
    #[DoesNotPerformAssertions]
    public function testAnalyze(): void
    {
        for ($i = 0; $i < 100; ++$i) {
            $seed = random_int(1, PHP_INT_MAX);
            $analyzer = new DebugAnalyzer(seed: $seed, depth: 3);
            $analyzer->analyze('/fake/path');
        }
    }
}
