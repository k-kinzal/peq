<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\PhpParserGrammar;

/**
 * @internal
 */
#[CoversNothing]
#[Medium]
final class GrammarCoverageContractTest extends TestCase
{
    public function testEverySyntaxTheParserProducesIsClassified(): void
    {
        $classified = array_merge(array_keys(PhpParserGrammar::dependencyProducing()), PhpParserGrammar::notDependencyProducing());
        $unclassified = array_diff(PhpParserGrammar::nodeTypes(), $classified);

        self::assertSame([], array_values($unclassified), 'Syntax the parser produces that is classified neither way: '.implode(', ', $unclassified));
    }

    public function testEveryClassifiedSyntaxIsOneTheParserProduces(): void
    {
        $classified = array_merge(array_keys(PhpParserGrammar::dependencyProducing()), PhpParserGrammar::notDependencyProducing());
        $extra = array_diff($classified, PhpParserGrammar::nodeTypes());

        self::assertSame([], array_values($extra), 'Classified syntax the parser does not produce: '.implode(', ', $extra));
    }

    #[DataProviderExternal(PhpParserGrammar::class, 'dependencyProducingClasses')]
    public function testEverySyntaxRelationsAreReadFromExists(string $fqcn): void
    {
        self::assertTrue(class_exists($fqcn), sprintf('The syntax "%s" is classified as producing relations but does not exist.', $fqcn));
    }

    #[DataProviderExternal(PhpParserGrammar::class, 'notDependencyProducingClasses')]
    public function testEverySyntaxNothingIsReadFromExists(string $fqcn): void
    {
        self::assertTrue(class_exists($fqcn), sprintf('The syntax "%s" is classified as producing no relations but does not exist.', $fqcn));
    }

    public function testNoSyntaxIsClassifiedBothWays(): void
    {
        $overlap = array_intersect(array_keys(PhpParserGrammar::dependencyProducing()), PhpParserGrammar::notDependencyProducing());

        self::assertSame([], array_values($overlap), 'Syntax classified both as producing relations and as producing none: '.implode(', ', $overlap));
    }
}
