<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Analyzer\Analyzer;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\NativeAnalyzer\NativeAnalyzer;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use App\Analyzer\PhpStanAnalyzer\WorkingDirectory;
use App\Gql\Element\GraphProjection;
use App\Gql\Execution\QueryExecution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
#[Large]
final class UnresolvedCallLocationTest extends TestCase
{
    /**
     * @throws \App\Gql\GqlException
     */
    #[DataProvider('providerAnalyzers')]
    public function testUnknownReceiversAndDynamicNamesUseDistinctSourceLocations(Analyzer $analyzer): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-call-location-'.uniqid());
        $file = $directory->write('source.php', <<<'PHP'
            <?php
            function invoke($first, $second, $name) {
                $first->run(); $second?->run();
                $first->$name();
            }
            PHP);
        $file = realpath($file);
        self::assertNotFalse($file);

        $graph = $analyzer->analyze($file);
        $answer = (new QueryExecution(GraphProjection::of($graph)))->query('MATCH (f)-[e:methodCall]->(u:`Unknown`) WHERE e.resolution = "unresolved" RETURN u.id, e.line, e.column, e.`offset` ORDER BY e.line, e.column');
        $directory->delete();

        self::assertSame([
            ['unresolved-call@'.$file.':3:5', '3', '5', '52'],
            ['unresolved-call@'.$file.':3:20', '3', '20', '67'],
            ['unresolved-call@'.$file.':4:5', '4', '5', '88'],
        ], array_map(static fn ($row): array => array_map(static fn ($value): string => $value->toText(), $row->values), $answer->rows));
        self::assertEquals(new FileMeta($file, 3, 5, 52), $graph->nodeNamed('unresolved-call@'.$file.':3:5')?->meta());
        self::assertEquals(new FileMeta($file, 3, 20, 67), $graph->nodeNamed('unresolved-call@'.$file.':3:20')?->meta());
        self::assertEquals(new FileMeta($file, 4, 5, 88), $graph->nodeNamed('unresolved-call@'.$file.':4:5')?->meta());
    }

    /**
     * @return iterable<string, array{Analyzer}>
     */
    public static function providerAnalyzers(): iterable
    {
        yield 'native' => [new NativeAnalyzer()];

        yield 'phpstan' => [new PhpStanAnalyzer()];
    }
}
