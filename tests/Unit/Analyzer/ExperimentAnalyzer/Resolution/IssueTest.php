<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Resolution;

use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class IssueTest extends TestCase
{
    public function testValueRetainsStableSupportInformation(): void
    {
        $source = new \App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence('x', 'unknown', null, 3, 2, 4, 'eval($a)');
        $issue = new \App\Analyzer\ExperimentAnalyzer\Resolution\Issue('UNSUPPORTED_EXPRESSION', 'x', 'rules/v1', 'Unmodeled effects', $source);
        self::assertSame('x', $issue->nodeId);
        self::assertSame(['value', 'control', 'effects'], $issue->affects);
        self::assertSame(3, $issue->source->line);
    }
}
