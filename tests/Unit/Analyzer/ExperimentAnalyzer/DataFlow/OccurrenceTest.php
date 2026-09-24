<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\DataFlow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(\App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence::class)]
final class OccurrenceTest extends TestCase
{
    public function testOccurrenceRetainsTheByteColumnAndSourceSpan(): void
    {
        $node = new \App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence('id', 'read', '$value', 3, 9, 3, '$value');
        self::assertSame(9, $node->column);
        self::assertSame('$value', $node->text);
        self::assertSame(3, $node->endLine);
    }
}
