<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\DebugAnalyzer\Provider;

use App\Analyzer\DebugAnalyzer\Provider\GraphGenerator;
use Faker\Generator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class GraphGeneratorTest extends TestCase
{
    #[Test]
    public function testInstantiation(): void
    {
        $generator = new GraphGenerator();
        self::assertInstanceOf(Generator::class, $generator);
    }
}
