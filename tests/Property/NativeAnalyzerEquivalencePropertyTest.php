<?php

declare(strict_types=1);

namespace Tests\Property;

use App\Analyzer\Graph\GraphSnapshot;
use App\Analyzer\NativeAnalyzer\NativeAnalyzer;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use Eris\Generator\SequenceGenerator;
use Eris\Generator\TupleGenerator;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The two engines, checked against each other on programs nobody wrote.
 *
 * A corpus is a list of the cases its author thought of, and the cases an engine gets
 * wrong are by definition the ones nobody thought of. So the programs here are drawn
 * rather than written: a fixed cast of declarations, and a drawn sequence of
 * expressions dropped into drawn places — a method body, a closure, an anonymous
 * class, a function declared inside another one — which is where the two engines
 * decide what a relation belongs to and therefore where they can disagree.
 *
 * Drawing them is only half of it. When a program does make the engines disagree,
 * Eris shrinks it to the shortest one that still does, which turns a failure from a
 * fifty-line program nobody can read into the one expression that causes it.
 *
 * @group pbt
 *
 * @internal
 */
#[CoversClass(NativeAnalyzer::class)]
#[Group('pbt')]
#[Large]
final class NativeAnalyzerEquivalencePropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Checks that a drawn program describes the same graph to both engines.
     */
    public function testBothEnginesDescribeTheSameGraphOfADrawnProgram(): void
    {
        $this->limitTo(25)
            ->forAll(new SequenceGenerator(new TupleGenerator([
                Generators::choose(0, count(self::places()) - 1),
                Generators::choose(0, count(self::expressions()) - 1),
            ])))
            ->then(static function (array $drawn): void {
                $path = self::writeProgram(self::renderProgram($drawn));
                $reference = GraphSnapshot::of((new PhpStanAnalyzer())->analyze($path));
                $candidate = GraphSnapshot::of((new NativeAnalyzer())->analyze($path));

                self::assertSame(
                    $reference->fingerprint(),
                    $candidate->fingerprint(),
                    file_get_contents($path)."\n".$candidate->differenceFrom($reference)->describe(),
                );
            })
        ;
    }

    /**
     * Returns the places a drawn expression can be written.
     *
     * Each place is a template with one slot for the expression and one for a number
     * that keeps drawn declarations from colliding with each other.
     *
     * @return list<string> The places, as templates
     */
    public static function places(): array
    {
        return [
            '$drawn%2$d = %1$s;',
            'if ($this->held > 0) { $drawn%2$d = %1$s; }',
            'try { $drawn%2$d = %1$s; } catch (Drawn\Other $caught) { $caught->getMessage(); }',
            '$closure%2$d = function () { return %1$s; };',
            '$arrow%2$d = fn () => %1$s;',
            '$nested%2$d = function () { $deeper = function () { return %1$s; }; };',
            '$anonymous%2$d = new class { public function inner%2$d(): mixed { return %1$s; } };',
            'foreach ([%1$s] as $item%2$d) { $item%2$d; }',
        ];
    }

    /**
     * Returns the expressions a drawn program can be built out of.
     *
     * @return list<string> The expressions
     */
    public static function expressions(): array
    {
        return [
            'new Drawn\Other()',
            'new self()',
            'new static()',
            'Drawn\Other::made()',
            'Drawn\Other::LIMIT',
            'Drawn\Other::$counter',
            'Drawn\Other::class',
            '$this->helper()',
            '$this?->helper()',
            '$this->held',
            '$this?->held',
            'self::helper()',
            'static::helper()',
            'parent::inherited()',
            'parent::class',
            'Drawn\drawnFunction()',
            'drawnFunction()',
            'strlen(\'drawn\')',
            '$this instanceof Drawn\Other',
            '$this->shared()',
        ];
    }

    /**
     * Renders a drawn program.
     *
     * @param array<array-key, mixed> $drawn One pair of a place and an expression per statement
     *
     * @return string The program
     */
    public static function renderProgram(array $drawn): string
    {
        $statements = [];
        foreach (array_values($drawn) as $position => $pair) {
            self::assertIsArray($pair);
            [$place, $expression] = [$pair[0], $pair[1]];
            self::assertIsInt($place);
            self::assertIsInt($expression);
            $statements[] = '        '.sprintf(self::places()[$place], self::expressions()[$expression], $position);
        }

        return str_replace('%STATEMENTS%', implode("\n", $statements), self::template());
    }

    /**
     * Returns the cast of declarations every drawn program is written into.
     *
     * @return string The program template, with one slot for the drawn statements
     */
    public static function template(): string
    {
        return <<<'PHP'
            <?php
            namespace Drawn;

            class Other { public const LIMIT = 1; public static int $counter = 0; public static function made(): self { return new self(); } }
            class Ancestor_ { public function inherited(): void {} }
            trait Shared { public function shared(): void {} }
            function drawnFunction(): void {}

            final class Subject extends Ancestor_ {
                use Shared;
                public int $held = 0;
                public function helper(): mixed { return null; }
                public function subject(): void {
            %STATEMENTS%
                }
            }
            PHP;
    }

    /**
     * Writes a drawn program out and returns where it was written.
     *
     * @param string $program The program
     *
     * @return string The file holding it
     *
     * @throws RuntimeException If the program cannot be written
     */
    public static function writeProgram(string $program): string
    {
        $file = sys_get_temp_dir().'/peq-drawn-'.md5($program).'.php';
        if (!is_file($file) && file_put_contents($file, $program) === false) {
            throw new RuntimeException(sprintf('Unable to write the file "%s"', $file));
        }

        $resolved = realpath($file);

        return $resolved === false ? $file : $resolved;
    }
}
