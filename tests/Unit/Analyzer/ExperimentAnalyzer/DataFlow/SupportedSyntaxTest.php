<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\DataFlow;

use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class SupportedSyntaxTest extends TestCase
{
    public function testCheckRejectsGlobalState(): void
    {
        $this->expectException(\App\Analyzer\ExperimentAnalyzer\DataFlow\InspectionException::class);
        (new \App\Analyzer\ExperimentAnalyzer\DataFlow\SupportedSyntax())->check([new \PhpParser\Node\Stmt\Global_([new \PhpParser\Node\Expr\Variable('a')])]);
    }

    public function testNodeRejectsReferenceCaptures(): void
    {
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse('<?php function () use (&$a) {};');
        self::assertNotNull($parsed);
        $this->expectException(\App\Analyzer\ExperimentAnalyzer\DataFlow\InspectionException::class);
        (new \App\Analyzer\ExperimentAnalyzer\DataFlow\SupportedSyntax())->node($parsed[0]);
    }

    public function testUnsupportedAcceptsOrdinaryAssignments(): void
    {
        self::assertNull((new \App\Analyzer\ExperimentAnalyzer\DataFlow\SupportedSyntax())->unsupported(new \PhpParser\Node\Expr\Assign(new \PhpParser\Node\Expr\Variable('a'), new \PhpParser\Node\Scalar\Int_(1))));
    }
}
