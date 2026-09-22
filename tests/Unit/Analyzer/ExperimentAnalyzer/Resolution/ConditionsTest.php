<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\ExperimentAnalyzer\Resolution;

use App\Analyzer\ExperimentAnalyzer\DataFlow\DependencyGraph;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Inspection;
use App\Analyzer\ExperimentAnalyzer\DataFlow\Slice;
use App\Analyzer\Graph\Direction;
use PhpParser\Node\Stmt\Function_;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Analyzer\ExperimentAnalyzer')]
final class ConditionsTest extends TestCase
{
    public function testObserveRefusesToCertifyCorrelatedAlternatives(): void
    {
        $source = "<?php function f(\$a) {\n\$x = 0; if (\$a) { \$x = 1; }\nif (!\$a) { return \$x; }\n}";
        $parsed = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
        self::assertNotNull($parsed);
        self::assertInstanceOf(Function_::class, $parsed[0]);
        $graph = (new Inspection())->analyze($parsed[0], new DependencyGraph('f', '/f.php', $source));
        $slice = Slice::of($graph, $graph->select(3, 'x'), Direction::Uses, null);
        self::assertFalse($slice->analysis->complete);
        self::assertSame(['PATH_CORRELATION'], array_column($slice->analysis->issues, 'code'));
    }
}
