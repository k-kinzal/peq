<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Flow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(\App\Analyzer\ExperimentAnalyzer\Flow\Exits::class)]
#[\PHPUnit\Framework\Attributes\UsesNamespace('App\Analyzer\ExperimentAnalyzer')]
final class ExitsTest extends TestCase
{
    public function testAbsorbKeepsOnlyExitsForAnOuterLoop(): void
    {
        $state = new \App\Analyzer\ExperimentAnalyzer\Flow\State();
        $inner = new \App\Analyzer\ExperimentAnalyzer\Flow\Exits(null);
        $inner->breaks = [1 => [$state], 2 => [$state]];
        $inner->continues = [1 => [$state], 3 => [$state]];
        $outer = new \App\Analyzer\ExperimentAnalyzer\Flow\Exits(null);
        $outer->absorb($inner, true);
        self::assertSame([1 => [$state]], $outer->breaks);
        self::assertSame([2 => [$state]], $outer->continues);
        self::assertNull($outer->normal);
    }

    public function testCombineConsumesExactlyOneNestedLevel(): void
    {
        $state = new \App\Analyzer\ExperimentAnalyzer\Flow\State();
        self::assertSame([1 => [$state]], \App\Analyzer\ExperimentAnalyzer\Flow\Exits::combine([], [2 => [$state]], true));
    }

    public function testValidateRejectsAnExitWhoseLoopDepthCannotBeConsumed(): void
    {
        $source = '<?php function f() { while (true) { break 2; } }';
        $parsed = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(\PhpParser\Node\Stmt\Function_::class, $parsed[0]);
        $graph = (new \App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection())->analyze($parsed[0], new \App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph('f', '/f.php', $source));
        self::assertSame(['INVALID_LOOP_EXIT'], array_column($graph->issues, 'code'));
        self::assertSame(['break 2;'], array_map(static fn ($issue) => $issue->source->text, array_values($graph->issues)));
    }

    public function testValidateDoesNotBlameAPreviousValidBreakForAnInvalidContinue(): void
    {
        $source = '<?php function f() { while (true) { while (true) { break; } continue 2; } }';
        $parsed = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(\PhpParser\Node\Stmt\Function_::class, $parsed[0]);
        $graph = (new \App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection())->analyze($parsed[0], new \App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph('f', '/f.php', $source));
        self::assertSame(['INVALID_LOOP_EXIT'], array_column($graph->issues, 'code'));
        self::assertSame(['continue 2;'], array_map(static fn ($issue) => $issue->source->text, array_values($graph->issues)));
    }
}
