<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\NativeAnalyzer;

use App\Analyzer\NativeAnalyzer\AnalysisScope;
use App\Analyzer\NativeAnalyzer\BodyWalker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\GraphSpelling;
use Tests\Fixture\Analyzer\ParsedSnippet;

/**
 * @internal
 */
#[CoversClass(BodyWalker::class)]
#[Medium]
final class BodyWalkerTest extends TestCase
{
    /**
     * @param list<string> $expected The relations the body is expected to write
     */
    #[DataProvider('providerBodies')]
    public function testRelationsReadsWhatABodyWrites(string $code, array $expected): void
    {
        $index = ParsedSnippet::index(['Walked.php' => $code]);
        $body = $index->sources()[0]->methodBody('App\Invoice', 'total') ?? [];
        $scope = AnalysisScope::inFile($index, $index->sources()[0]->path)->enteringClass('App\Invoice', null)->enteringMethod('total');

        self::assertSame($expected, GraphSpelling::of((new BodyWalker())->relations($body, $scope)));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerBodies(): iterable
    {
        yield 'what the body itself reaches out to' => [
            "<?php\nnamespace App;\nclass Invoice { public function total(): void { \$money = new \\App\\Money(); } }\n",
            ['App\Invoice::total -[instantiation]-> App\Money'],
        ];

        yield 'what a closure in the body reaches out to' => [
            "<?php\nnamespace App;\nclass Invoice { public function total(): void { \$closure = function () { return new \\App\\Money(); }; } }\n",
            ['App\Invoice::total -[instantiation]-> App\Money'],
        ];

        yield 'what an anonymous class in the body reaches out to' => [
            "<?php\nnamespace App;\nclass Invoice { public function total(): void { \$made = new class { public function inner(): mixed { return new \\App\\Money(); } }; } }\n",
            ['App\Invoice::total -[instantiation]-> App\Money'],
        ];

        yield 'a body that reaches nothing' => [
            "<?php\nnamespace App;\nclass Invoice { public function total(): int { return 1 + 1; } }\n",
            [],
        ];
    }
}
