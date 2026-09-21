<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Flow;

use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class CaptureVariablesTest extends TestCase
{
    public function testFindRespectsNestedArrowParameterScopes(): void
    {
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse('<?php fn ($x) => fn ($y) => $x + $y + $outer;');
        self::assertNotNull($parsed);
        self::assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $parsed[0]);
        $variables = (new \App\Analyzer\ExperimentAnalyzer\Flow\CaptureVariables())->find($parsed[0]->expr);
        self::assertSame(['outer'], array_map(static fn (\PhpParser\Node\Expr\Variable $node): \PhpParser\Node\Expr|string => $node->name, $variables));
    }
}
