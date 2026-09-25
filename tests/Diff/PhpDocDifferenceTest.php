<?php

declare(strict_types=1);

namespace Tests\Diff;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\GraphSnapshot;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use Generator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * PHPStan's documented PHPDoc syntax, observed through both graph engines.
 *
 * @see https://phpstan.org/writing-php-code/phpdocs-basics
 * @see https://phpstan.org/writing-php-code/phpdoc-types
 *
 * @internal
 */
#[CoversNothing]
#[Large]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PhpDocDifferenceTest extends TestCase
{
    /**
     * Checks attribution and reverse traversal on every PHP version PHPStan reads.
     */
    #[DataProvider('providerSupportedVersions')]
    public function testReferencesRetainTheirOwnersAndLocations(int $version): void
    {
        $source = <<<'PHP'
            <?php
            namespace Doc;
            class Item {}
            /** @mixin Item */
            class Subject {
                /** @var Item */
                public $first, $second;
                /** @return Item */
                public function run() {
                    /** @var Item $value */
                    $value = null;
                }
            }
            PHP;
        $directory = sys_get_temp_dir().'/peq-phpdoc-owners-'.$version;
        is_dir($directory) || mkdir($directory, 0o777, true);
        file_put_contents($directory.'/source.php', $source);
        $graph = (new PhpStanAnalyzer(phpVersion: $version))->analyze($directory);
        $references = array_filter($graph->forwardEdges(), static fn (Edge $edge): bool => $edge->kind() === EdgeKind::PhpDoc);
        $locations = array_map(static fn (Edge $edge): array => [$edge->from()->toString(), $edge->to()->toString(), $edge->meta()->line], $references);
        sort($locations);
        $native = new Process([PHP_BINARY, '-r', <<<'PHP'
            require $argv[1];
            $graph = (new App\Analyzer\NativeAnalyzer\NativeAnalyzer(phpVersion: (int) $argv[3]))->analyze($argv[2]);
            echo App\Analyzer\Graph\GraphSnapshot::of($graph)->toString();
            PHP, dirname(__DIR__, 2).'/vendor/autoload.php', $directory, (string) $version]);
        $native->mustRun();

        self::assertSame([
            ['Doc\Subject', 'Doc\Item', 4],
            ['Doc\Subject::first', 'Doc\Item', 6],
            ['Doc\Subject::run', 'Doc\Item', 8],
            ['Doc\Subject::run', 'Doc\Item', 10],
            ['Doc\Subject::second', 'Doc\Item', 6],
        ], $locations);
        self::assertCount(5, $graph->edges(\App\Analyzer\Graph\NodeId\ClassNodeId::of('Doc\Item')));
        self::assertSame(GraphSnapshot::of($graph)->toString(), $native->getOutput());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function providerSupportedVersions(): iterable
    {
        yield 'PHP 7.1' => [70100];

        yield 'PHP 7.2' => [70200];

        yield 'PHP 7.3' => [70300];

        yield 'PHP 7.4' => [70400];

        yield 'PHP 8.0' => [80000];

        yield 'PHP 8.1' => [80100];

        yield 'PHP 8.2' => [80200];

        yield 'PHP 8.3' => [80300];

        yield 'PHP 8.4' => [80400];

        yield 'PHP 8.5' => [80500];
    }

    /**
     * @param list<string> $expectedTypes
     */
    #[DataProvider('providerSources')]
    public function testBothEnginesReadTheSameDocumentedSource(string $source, array $expectedTypes): void
    {
        $directory = sys_get_temp_dir().'/peq-phpdoc-diff-'.hash('sha256', $source);
        is_dir($directory) || mkdir($directory, 0o777, true);
        file_put_contents($directory.'/source.php', $source);

        $referenceGraph = (new PhpStanAnalyzer())->analyze($directory);
        $reference = GraphSnapshot::of($referenceGraph);
        $references = array_filter($referenceGraph->forwardEdges(), static fn (Edge $edge): bool => $edge->kind() === EdgeKind::PhpDoc);
        $types = array_values(array_unique(array_map(static fn (Edge $edge): string => $edge->to()->toString(), $references)));
        sort($types);
        $native = new Process([PHP_BINARY, '-r', <<<'PHP'
            require $argv[1];
            $graph = (new App\Analyzer\NativeAnalyzer\NativeAnalyzer())->analyze($argv[2]);
            if (class_exists(PHPStan\DependencyInjection\ContainerFactory::class, false)) {
                throw new RuntimeException('Native analysis loaded the PHPStan engine.');
            }
            echo App\Analyzer\Graph\GraphSnapshot::of($graph)->toString();
            PHP, dirname(__DIR__, 2).'/vendor/autoload.php', $directory]);
        $native->mustRun();

        self::assertNotSame([], $reference->nodes);
        self::assertSame($expectedTypes, $types);
        self::assertSame($reference->toString(), $native->getOutput());
    }

    /**
     * @return Generator<string, array{string, list<string>}>
     */
    public static function providerSources(): Generator
    {
        foreach ([
            'class' => ['Target', ['Doc\Target']],
            'fully qualified class' => ['\Doc\Target', ['Doc\Target']],
            'import alias' => ['Imported', ['Doc\Target']],
            'nullable' => ['?Target', ['Doc\Target']],
            'union' => ['Target|Other|null', ['Doc\Other', 'Doc\Target']],
            'intersection' => ['Target&Contract', ['Doc\Contract', 'Doc\Target']],
            'parenthesized DNF' => ['(Target&Contract)|Other', ['Doc\Contract', 'Doc\Other', 'Doc\Target']],
            'array suffix' => ['Target[]', ['Doc\Target']],
            'nested arrays' => ['array<string, list<Target>>', ['Doc\Target']],
            'nonempty array' => ['non-empty-array<int, Target>', ['Doc\Target']],
            'nonempty list' => ['non-empty-list<Target>', ['Doc\Target']],
            'iterable' => ['iterable<string, Target>', ['Doc\Target']],
            'generic' => ['Box<Target>', ['Doc\Box', 'Doc\Target']],
            'covariant argument' => ['Box<covariant Target>', ['Doc\Box', 'Doc\Target']],
            'contravariant argument' => ['Box<contravariant Target>', ['Doc\Box', 'Doc\Target']],
            'star projection' => ['Box<*>', ['Doc\Box']],
            'array shape' => ['array{item: Target, optional?: Other, nested: array{Target}}', ['Doc\Other', 'Doc\Target']],
            'unsealed array shape' => ['array{item: Target, ...<string, Other>}', ['Doc\Other', 'Doc\Target']],
            'object shape' => ['object{item: Target, optional?: Other}', ['Doc\Other', 'Doc\Target']],
            'tuple' => ['array{Target, Other}', ['Doc\Other', 'Doc\Target']],
            'callable' => ['callable(Target, Other=): Target', ['Doc\Other', 'Doc\Target']],
            'closure' => ['\Closure(Target): Other', ['Closure', 'Doc\Other', 'Doc\Target']],
            'pure callable' => ['pure-callable(Target): Other', ['Doc\Other', 'Doc\Target']],
            'class string' => ['class-string<Target>', ['Doc\Target']],
            'conditional type' => ['(Target is Contract ? Target : Other)', ['Doc\Contract', 'Doc\Other', 'Doc\Target']],
            'argument conditional' => ['($input is Target ? Target : Other)', ['Doc\Other', 'Doc\Target']],
            'constant type' => ['Target::VALUE', ['Doc\Target']],
            'constant wildcard' => ['Target::VALUE_*', ['Doc\Target']],
            'key of' => ['key-of<Target::MAP>', ['Doc\Target']],
            'value of' => ['value-of<Target::MAP>', ['Doc\Target']],
            'offset access' => ['array{item: Target}[\'item\']', ['Doc\Target']],
            'integer range' => ['int<0, max>', []],
            'string refinements' => ['non-empty-string|literal-string|numeric-string', []],
            'scalar literals' => ["'Target'|42|1.5|true|false|null", []],
            'builtin aliases' => ['integer|boolean|double|resource|mixed|never|void', []],
            'multiline shape' => ["array{\n * item: Target,\n * optional?: Other,\n * }", ['Doc\Other', 'Doc\Target']],
        ] as $label => [$type, $expectedTypes]) {
            yield $label => [str_replace('__TYPE__', $type, <<<'PHP'
                <?php
                namespace Doc;
                use Doc\Target as Imported;
                interface Contract {}
                class Target implements Contract {
                    const VALUE = 'value';
                    const VALUE_ONE = 1;
                    const MAP = ['item' => 1];
                    public function work(): void {}
                }
                class Other {}
                /**
                 * @template T
                 */
                class Box {}
                class Subject {
                    /**
                     * @var __TYPE__
                     */
                    public $property;
                    /**
                     * @param __TYPE__ $input
                     * @return __TYPE__
                     */
                    public function run($input) {
                        $input->work();
                        return $input;
                    }
                }
                /**
                 * @param __TYPE__ $input
                 * @return __TYPE__
                 */
                function run($input) { $input->work(); return $input; }
                PHP), $expectedTypes];
        }
        foreach (['param', 'phpstan-param', 'psalm-param', 'phan-param', 'param-out', 'phpstan-param-out', 'psalm-param-out'] as $tag) {
            yield $tag => [str_replace('__TAG__', $tag, <<<'PHP'
                <?php
                namespace Doc;
                class Target { public function work(): void {} }
                /**
                 * @__TAG__ Target $input
                 */
                function run(&$input) { $input->work(); }
                PHP), ['Doc\Target']];
        }
        foreach (['return', 'phpstan-return', 'psalm-return', 'phan-return', 'phan-real-return', 'throws', 'phpstan-throws'] as $tag) {
            yield $tag => [str_replace('__TAG__', $tag, <<<'PHP'
                <?php
                namespace Doc;
                class Target extends \Exception {}
                /**
                 * @__TAG__ Target
                 */
                function run() { return new Target(); }
                PHP), ['Doc\Target']];
        }
        foreach (['var', 'phpstan-var', 'psalm-var', 'phan-var'] as $tag) {
            yield $tag => [str_replace('__TAG__', $tag, <<<'PHP'
                <?php
                namespace Doc;
                class Target { public function work(): void {} }
                class Subject {
                    /**
                     * @__TAG__ Target
                     */
                    public $property;
                    public function run($input) {
                        /**
                         * @__TAG__ Target $input
                         */
                        $input->work();
                        /**
                         * @__TAG__ Target
                         */
                        $local = $input;
                        $local->work();
                    }
                }
                PHP), ['Doc\Target']];
        }
        foreach (['property', 'property-read', 'property-write', 'phpstan-property', 'phpstan-property-read', 'phpstan-property-write', 'psalm-property', 'psalm-property-read', 'psalm-property-write', 'phan-property', 'phan-property-read', 'phan-property-write'] as $tag) {
            yield $tag => [str_replace('__TAG__', $tag, <<<'PHP'
                <?php
                namespace Doc;
                class Target { public function work(): void {} }
                /**
                 * @__TAG__ Target $property
                 */
                class Subject {
                    public function run() { $this->property->work(); }
                }
                PHP), ['Doc\Target']];
        }
        foreach (['method', 'phpstan-method', 'psalm-method', 'phan-method'] as $tag) {
            yield $tag => [str_replace('__TAG__', $tag, <<<'PHP'
                <?php
                namespace Doc;
                class Target { public function work(): void {} }
                /**
                 * @__TAG__ Target factory(Target $input, ?Target $optional = null)
                 * @__TAG__ static Target create()
                 */
                class Subject {
                    public function run() { $this->factory(new Target())->work(); }
                }
                PHP), ['Doc\Target']];
        }
        foreach (['template', 'template-covariant', 'template-contravariant', 'phpstan-template', 'psalm-template', 'phan-template', 'phpstan-template-covariant', 'phpstan-template-contravariant', 'psalm-template-covariant', 'psalm-template-contravariant'] as $tag) {
            yield $tag => [str_replace('__TAG__', $tag, <<<'PHP'
                <?php
                namespace Doc;
                class Target { public function work(): void {} }
                /**
                 * @__TAG__ T of Target = Target
                 */
                class Subject {
                    /**
                     * @var T
                     */
                    public $property;
                    /**
                     * @param T $input
                     * @return T
                     */
                    public function run($input) { $input->work(); return $input; }
                }
                PHP), ['Doc\Target']];
        }
        foreach (['phpstan-assert', 'phpstan-assert-if-true', 'phpstan-assert-if-false', 'psalm-assert', 'psalm-assert-if-true', 'psalm-assert-if-false', 'phan-assert', 'phan-assert-if-true', 'phan-assert-if-false'] as $tag) {
            yield $tag => [str_replace('__TAG__', $tag, <<<'PHP'
                <?php
                namespace Doc;
                class Target { public function work(): void {} }
                /**
                 * @__TAG__ Target $input
                 */
                function check($input): bool { return $input instanceof Target; }
                function run($input): void { if (check($input)) { $input->work(); } }
                PHP), ['Doc\Target']];
        }

        foreach (['extends', 'template-extends', 'phpstan-extends', 'phan-extends', 'phan-inherits'] as $tag) {
            yield $tag => [str_replace('__TAG__', $tag, <<<'PHP'
                <?php
                namespace Doc;
                class Target {}
                /** @template T */
                class Box {}
                /** @__TAG__ Box<Target> */
                class Subject extends Box {}
                PHP), ['Doc\Box', 'Doc\Target']];
        }
        foreach (['implements', 'template-implements', 'phpstan-implements'] as $tag) {
            yield $tag => [str_replace('__TAG__', $tag, <<<'PHP'
                <?php
                namespace Doc;
                class Target {}
                /** @template T */
                interface Box {}
                /** @__TAG__ Box<Target> */
                class Subject implements Box {}
                PHP), ['Doc\Box', 'Doc\Target']];
        }
        foreach (['use', 'template-use', 'phpstan-use'] as $tag) {
            yield $tag => [str_replace('__TAG__', $tag, <<<'PHP'
                <?php
                namespace Doc;
                class Target {}
                /** @template T */
                trait Box {}
                class Subject {
                    /** @__TAG__ Box<Target> */
                    use Box;
                }
                PHP), ['Doc\Box', 'Doc\Target']];
        }
        foreach (['mixin', 'phan-mixin', 'phpstan-require-extends', 'phpstan-require-implements', 'psalm-require-extends', 'psalm-require-implements'] as $tag) {
            yield $tag => [str_replace('__TAG__', $tag, <<<'PHP'
                <?php
                namespace Doc;
                class Target {}
                /** @__TAG__ Target */
                trait Subject {}
                class Consumer { use Subject; }
                PHP), ['Doc\Target']];
        }
        foreach (['phpstan-self-out', 'phpstan-this-out', 'psalm-self-out', 'psalm-this-out'] as $tag) {
            yield $tag => [str_replace('__TAG__', $tag, <<<'PHP'
                <?php
                namespace Doc;
                class Target {}
                class Subject {
                    /** @__TAG__ Target */
                    public function run(): void {}
                }
                PHP), ['Doc\Target']];
        }
        foreach (['phpstan-param-closure-this', 'param-closure-this'] as $tag) {
            yield $tag => [str_replace('__TAG__', $tag, <<<'PHP'
                <?php
                namespace Doc;
                class Target {}
                /** @__TAG__ Target $callback */
                function run(callable $callback): void {}
                PHP), ['Doc\Target']];
        }
        foreach (['phpstan-type', 'psalm-type', 'phan-type'] as $tag) {
            yield $tag => [str_replace('__TAG__', $tag, <<<'PHP'
                <?php
                namespace Doc;
                class Target {}
                /** @__TAG__ Row array{item: Target} */
                class Subject {
                    /** @return Row */
                    public function run() { return []; }
                }
                PHP), ['Doc\Target']];
        }
        foreach (['phpstan-import-type', 'psalm-import-type'] as $tag) {
            yield $tag => [str_replace('__TAG__', $tag, <<<'PHP'
                <?php
                namespace Doc;
                class Target {}
                /** @phpstan-type Row array{item: Target} */
                class Schema {}
                /** @__TAG__ Row from Schema as ImportedRow */
                class Subject {
                    /** @return ImportedRow */
                    public function run() { return []; }
                }
                PHP), ['Doc\Schema', 'Doc\Target']];
        }

        yield 'scope dependent types' => [<<<'PHP'
            <?php
            namespace Doc;
            class Base {}
            class Subject extends Base {
                /** @return self */
                public function first() { return $this; }
                /** @return static */
                public function second() { return $this; }
                /** @return $this */
                public function third() { return $this; }
                /** @return parent */
                public function fourth() { return $this; }
            }
            PHP, ['Doc\Base', 'Doc\Subject']];

        yield 'namespace and grouped imports' => [<<<'PHP'
            <?php
            namespace Doc\Model { class Target {} class Other {} }
            namespace Doc\Consumer {
                use Doc\Model\{Target as Item, Other};
                use function Doc\Model\functionName;
                use const Doc\Model\CONSTANT_NAME;
                /** @return array{Item, Other, \Doc\Model\Target} */
                function run() { return []; }
            }
            PHP, ['Doc\Model\Other', 'Doc\Model\Target']];

        yield 'template shadowing and recursive aliases' => [<<<'PHP'
            <?php
            namespace Doc;
            class Target {}
            class Other {}
            /**
             * @template T of Target
             * @phpstan-type Recursive array{next?: Recursive, value: Target}
             */
            class Subject {
                /**
                 * @template T of Other
                 * @param T $value
                 * @return Recursive
                 */
                public function run($value) { return []; }
            }
            PHP, ['Doc\Other', 'Doc\Target']];

        yield 'prefixed and ordinary annotations both document dependencies' => [<<<'PHP'
            <?php
            namespace Doc;
            class Target {}
            class Other {}
            /**
             * @param Other $input
             * @phpstan-param Target $input
             */
            function run($input) {}
            PHP, ['Doc\Other', 'Doc\Target']];

        yield 'malformed tag does not swallow the next valid tag' => [<<<'PHP'
            <?php
            namespace Doc;
            class Target {}
            /**
             * @param array{broken $input
             * @return Target
             */
            function run($input) {}
            PHP, ['Doc\Target']];

        yield 'metadata descriptions and unknown annotations are not class references' => [<<<'PHP'
            <?php
            namespace Doc;
            /**
             * Target is mentioned only in prose.
             * @deprecated Use Target instead.
             * @internal
             * @final
             * @readonly
             * @immutable
             * @phpstan-pure
             * @phpstan-impure
             * @phpstan-consistent-constructor
             * @no-named-arguments
             * @custom Target
             * @see Target
             */
            class Subject {}
            PHP, []];

        yield 'descriptions and shape labels cannot create fake dependencies' => [<<<'PHP'
            <?php
            namespace Doc;
            class Target {}
            /** @return array{Ghost: Target, "Other": 'Literal'} DescriptionClass */
            function run() { return []; }
            PHP, ['Doc\Target']];

        yield 'unused traits retain their documented dependencies' => [<<<'PHP'
            <?php
            namespace Doc;
            class Target {}
            trait Subject {
                /** @return Target */
                public function run() {}
            }
            PHP, ['Doc\Target']];
        foreach (['psalm-extends', 'psalm-implements', 'psalm-use', 'phpstan-mixin', 'psalm-mixin'] as $tag) {
            yield 'unsupported '.$tag => [str_replace('__TAG__', $tag, <<<'PHP'
                <?php
                namespace Doc;
                /** @__TAG__ Target */
                class Subject {}
                PHP), []];
        }

        yield 'method templates have their own scope' => [<<<'PHP'
            <?php
            namespace Doc;
            class Target {}
            /** @method T map<T of Target>(T $value) */
            class Subject {}
            PHP, ['Doc\Target']];

        yield 'callable templates have their own scope' => [<<<'PHP'
            <?php
            namespace Doc;
            class Target {}
            /** @param callable<T of Target>(T): T $callback */
            function run(callable $callback) {}
            PHP, ['Doc\Target']];

        yield 'scalar types with class-like names can be fully qualified' => [<<<'PHP'
            <?php
            namespace Doc;
            class Resource {}
            /** @return \Doc\Resource */
            function run() {}
            PHP, ['Doc\Resource']];

        yield 'constant array shape keys name their declaring class' => [<<<'PHP'
            <?php
            namespace Doc;
            class Target { const KEY = 'item'; }
            /** @return array{Target::KEY: int} */
            function run() { return []; }
            PHP, ['Doc\Target']];

        yield 'unknown Doctrine-style annotations are not PHPDoc types' => [<<<'PHP'
            <?php
            namespace Doc;
            /** @Custom(Target::class) */
            class Subject {}
            PHP, []];

        foreach (['psalm-inheritors'] as $tag) {
            yield $tag => ['<?php namespace Doc; /** @'.$tag.' Target */ class Subject {} class Target extends Subject {}', ['Doc\Target']];
        }
        foreach ([
            'consistent-constructor', 'impure', 'not-deprecated', 'phan', 'phpstan', 'psalm',
            'phan-immutable', 'phan-pure', 'phan-read-only', 'phan-side-effect-free',
            'phpstan-all-methods-impure', 'phpstan-all-methods-pure', 'phpstan-allow-private-mutation',
            'phpstan-immutable', 'phpstan-readonly', 'phpstan-readonly-allow-private-mutation',
            'psalm-allow-private-mutation', 'psalm-consistent-constructor', 'psalm-immutable',
            'psalm-pure', 'psalm-readonly', 'psalm-readonly-allow-private-mutation', 'pure',
        ] as $tag) {
            yield 'metadata '.$tag => ['<?php namespace Doc; /** @'.$tag.' Target */ class Subject {}', []];
        }
        foreach ([
            'param-immediately-invoked-callable', 'param-later-invoked-callable',
            'phpstan-param-immediately-invoked-callable', 'phpstan-param-later-invoked-callable',
            'pure-unless-callable-is-impure', 'phpstan-pure-unless-callable-is-impure',
            'pure-unless-parameter-passed', 'phpstan-pure-unless-parameter-passed',
        ] as $tag) {
            yield 'callable metadata '.$tag => ['<?php namespace Doc; /** @'.$tag.' $callback Target */ function run(callable $callback) {}', []];
        }

        yield 'invalid alias type' => ['<?php namespace Doc; /** @phpstan-type Broken array{ */ class Subject {}', []];

        yield 'sealed classes name allowed inheritors' => [<<<'PHP'
            <?php
            namespace Doc;
            /** @phpstan-sealed Target */
            class Subject {}
            class Target extends Subject {}
            PHP, ['Doc\Target']];
    }
}
