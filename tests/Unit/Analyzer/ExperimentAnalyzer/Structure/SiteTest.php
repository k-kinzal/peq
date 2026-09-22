<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Structure;

use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class SiteTest extends TestCase
{
    public function testValueDistinguishesContainmentFromPredicates(): void
    {
        $source = new \App\Analyzer\ExperimentAnalyzer\DataFlow\Occurrence('x', 'syntax', null, 2, 1, 2, 'new Foo');
        $site = new \App\Analyzer\ExperimentAnalyzer\Structure\Site($source, 'Expr_New', 'if', 'stmts[0]', 'Foo', 10, 17);
        self::assertSame('stmts[0]', $site->role);
        self::assertSame('Foo', $site->target);
        self::assertSame(10, $site->start);
    }
}
