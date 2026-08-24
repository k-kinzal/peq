<?php

declare(strict_types=1);

namespace Tests\Contract\Analyzer;

use PhpParser\Node;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Analyzer\PhpParserGrammar;

/**
 * @internal
 */#[CoversNothing]
#[Medium]
final class GrammarCoverageContractTest extends TestCase
{
    /**
     * Checks that all php parser node types are classified.
     */
    #[Test]
    public function testAllPhpParserNodeTypesAreClassified(): void
    {
        $discovered = PhpParserGrammar::nodeTypes();
        $dependencyProducing = array_keys(PhpParserGrammar::dependencyProducing());
        $notDependencyProducing = PhpParserGrammar::notDependencyProducing();
        $classified = array_merge($dependencyProducing, $notDependencyProducing);

        $unclassified = array_diff($discovered, $classified);
        $extra = array_diff($classified, $discovered);

        self::assertSame(
            [],
            array_values($unclassified),
            'The following PhpParser node types are not classified in either DEPENDENCY_PRODUCING or NOT_DEPENDENCY_PRODUCING: '
            .implode(', ', $unclassified),
        );

        self::assertSame(
            [],
            array_values($extra),
            'The following classified types were not discovered as concrete PhpParser node types: '
            .implode(', ', $extra),
        );
    }

    /**
     * Checks that dependency producing classes exist.
     */
    #[Test]
    public function testDependencyProducingClassesExist(): void
    {
        foreach (array_keys(PhpParserGrammar::dependencyProducing()) as $fqcn) {
            self::assertTrue(
                class_exists($fqcn),
                "DEPENDENCY_PRODUCING class does not exist: {$fqcn}",
            );
        }
    }

    /**
     * Checks that not dependency producing classes exist.
     */
    #[Test]
    public function testNotDependencyProducingClassesExist(): void
    {
        foreach (PhpParserGrammar::notDependencyProducing() as $fqcn) {
            self::assertTrue(
                class_exists($fqcn),
                "NOT_DEPENDENCY_PRODUCING class does not exist: {$fqcn}",
            );
        }
    }

    /**
     * Checks that no overlap between classifications.
     */
    #[Test]
    public function testNoOverlapBetweenClassifications(): void
    {
        $dependencyProducing = array_keys(PhpParserGrammar::dependencyProducing());
        $notDependencyProducing = PhpParserGrammar::notDependencyProducing();
        $overlap = array_intersect($dependencyProducing, $notDependencyProducing);

        self::assertSame(
            [],
            array_values($overlap),
            'The following types appear in both DEPENDENCY_PRODUCING and NOT_DEPENDENCY_PRODUCING: '
            .implode(', ', $overlap),
        );
    }
}
