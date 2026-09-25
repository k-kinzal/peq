<?php

declare(strict_types=1);

namespace Tests\Diff;

use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\GraphSnapshot;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[CoversClass(\App\Analyzer\Declaration\PhpDoc\DocBlock::class)]
#[CoversClass(\App\Analyzer\Declaration\PhpDoc\DocContext::class)]
#[CoversClass(\App\Analyzer\Declaration\PhpDoc\DocExpression::class)]
#[CoversClass(\App\Analyzer\Declaration\PhpDoc\DocIndex::class)]
#[CoversClass(\App\Analyzer\ReceiverBinding::class)]
#[CoversClass(\App\Analyzer\BodyCallRecorder::class)]
#[CoversClass(\App\Analyzer\CallSources::class)]
#[CoversClass(\App\Analyzer\CallEnrichment::class)]
#[UsesNamespace('App\Analyzer')]
#[Large]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PhpDocCallDifferenceTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerSources')]
    public function testDocumentationResolvesActualCalls(string $declarations, array $expected, int $version = PHP_VERSION_ID): void
    {
        $source = "<?php\nnamespace Doc;\nclass Target { function work() {} }\nclass Other { function work() {} }\n".$declarations;
        $directory = sys_get_temp_dir().'/peq-phpdoc-calls-'.hash('sha256', $source);
        is_dir($directory) || mkdir($directory, 0o777, true);
        file_put_contents($directory.'/source.php', $source);
        $graph = (new PhpStanAnalyzer(phpVersion: $version))->analyze($directory);
        $calls = [];
        foreach ($graph->forwardEdges() as $edge) {
            if ($edge->kind() === EdgeKind::MethodCall && str_ends_with($edge->to()->toString(), '::work')) {
                $calls[] = $edge->to()->toString();
            }
        }
        sort($calls);
        $native = new Process([PHP_BINARY, '-r', <<<'PHP'
            require $argv[1];
            $graph = (new App\Analyzer\NativeAnalyzer\NativeAnalyzer(phpVersion: (int) $argv[3]))->analyze($argv[2]);
            if (class_exists(PHPStan\DependencyInjection\ContainerFactory::class, false)) {
                throw new RuntimeException('Native analysis loaded the PHPStan engine.');
            }
            echo App\Analyzer\Graph\GraphSnapshot::of($graph)->toString();
            PHP, dirname(__DIR__, 2).'/vendor/autoload.php', $directory, (string) $version]);
        $native->mustRun();

        self::assertSame($expected, $calls);
        self::assertSame(GraphSnapshot::of($graph)->toString(), $native->getOutput());
    }

    /**
     * @return Generator<string, array{0: string, 1: list<string>, 2?: int}>
     */
    public static function providerSources(): Generator
    {
        foreach ([70100, 70200, 70300, 70400, 80000, 80100, 80200, 80300, 80400, 80500] as $version) {
            yield 'PHP '.$version => ['/** @phpstan-param Target $value */ function run($value) { $value->work(); }', ['Doc\Target::work'], $version];
        }
        foreach (['param', 'phpstan-param', 'psalm-param', 'phan-param'] as $tag) {
            yield $tag => ['/** @'.$tag.' Target $value */ function run($value) { $value->work(); }', ['Doc\Target::work']];
        }
        foreach (['var', 'phpstan-var', 'psalm-var', 'phan-var'] as $tag) {
            yield $tag.' property' => ['class Subject { /** @'.$tag.' Target */ public $value; function run() { $this->value->work(); } }', ['Doc\Target::work']];

            yield $tag.' local' => ['function run($value) { /** @'.$tag.' Target $value */ $value->work(); }', ['Doc\Target::work']];

            yield $tag.' assignment' => ['function run($input) { /** @'.$tag.' Target */ $value = $input; $value->work(); }', ['Doc\Target::work']];
        }
        foreach (['return', 'phpstan-return', 'psalm-return', 'phan-return', 'phan-real-return'] as $tag) {
            yield $tag.' function' => ['/** @'.$tag.' Target */ function make() {} function run() { make()->work(); }', ['Doc\Target::work']];

            yield $tag.' method' => ['class Factory { /** @'.$tag.' Target */ function make() {} } function run(Factory $f) { $f->make()->work(); }', ['Doc\Target::work']];

            yield $tag.' static method' => ['class Factory { /** @'.$tag.' Target */ static function make() {} } function run() { Factory::make()->work(); }', ['Doc\Target::work']];
        }

        yield 'prefixed parameter wins regardless of order' => ["/**\n * @phpstan-param Target \$value\n * @param Other \$value\n */ function run(\$value) { \$value->work(); }", ['Doc\Target::work']];

        yield 'psalm parameter wins over phan and ordinary' => ["/**\n * @psalm-param Target \$value\n * @phan-param Other \$value\n * @param Other \$value\n */ function run(\$value) { \$value->work(); }", ['Doc\Target::work']];

        yield 'array itself is not its elements' => ['/** @param list<Target> $values */ function run($values) { $values->work(); }', []];

        yield 'list element' => ['/** @param list<Target> $values */ function run($values) { $values[0]->work(); }', ['Doc\Target::work']];

        yield 'array suffix element' => ['/** @param Target[] $values */ function run($values) { $values[0]->work(); }', ['Doc\Target::work']];

        yield 'array shape element' => ["/** @param array{item: Target, other: Other} \$values */ function run(\$values) { \$values['item']->work(); }", ['Doc\Target::work']];

        yield 'foreach element' => ['/** @param iterable<Target> $values */ function run($values) { foreach ($values as $value) { $value->work(); } }', ['Doc\Target::work']];

        yield 'bounded template' => ["/**\n * @template T of Target\n * @param T \$value\n */ function run(\$value) { \$value->work(); }", ['Doc\Target::work']];

        yield 'local alias' => ['/** @phpstan-type Item Target */ class Subject { /** @param Item $value */ function run($value) { $value->work(); } }', ['Doc\Target::work']];

        yield 'imported alias' => ["/** @phpstan-type Item Target */ class Schema {}\n/** @phpstan-import-type Item from Schema as Imported */ class Subject { /** @param Imported \$value */ function run(\$value) { \$value->work(); } }", ['Doc\Target::work']];

        yield 'union' => ['/** @param Target|Other $value */ function run($value) { $value->work(); }', ['Doc\Other::work', 'Doc\Target::work']];

        yield 'closure own parameter' => ['function run() { $callback = /** @param Target $value */ function ($value) { $value->work(); }; }', ['Doc\Target::work']];

        yield 'separate closure scope' => ['/** @param Target $value */ function run($value) { $callback = function ($value) { $value->work(); }; }', []];

        yield 'ordinary and PHPStan var precedence' => ["function run(\$value) { /**\n * @phpstan-var Target \$value\n * @var Other \$value\n */ \$value->work(); }", ['Doc\Target::work']];

        yield 'return array element' => ['/** @return list<Target> */ function make() {} function run() { make()[0]->work(); }', ['Doc\Target::work']];

        yield 'class string is not the object' => ['/** @param class-string<Target> $type */ function run($type) { $type->work(); }', []];

        yield 'param out is not an input declaration' => ['/** @param-out Target $value */ function run(&$value) { $value->work(); }', []];

        yield 'explicit var overrides an inferred assignment' => ['/** @return Other */ function make() {} function run() { /** @var Target */ $value = make(); $value->work(); }', ['Doc\Target::work']];

        yield 'unsealed array shape element' => ["/** @param array{item: Other, ...<string, Target>} \$values */ function run(\$values) { \$values['extra']->work(); }", ['Doc\Target::work']];

        yield 'promoted property var' => ['class Subject { function __construct(/** @var Target */ public $value) {} function run() { $this->value->work(); } }', ['Doc\Target::work']];

        yield 'promoted property param' => ['class Subject { /** @param Target $value */ function __construct(public $value) {} function run() { $this->value->work(); } }', ['Doc\Target::work']];

        yield 'inherited return' => ['class Base { /** @return Target */ function make() {} } class Child extends Base { function make() {} } function run(Child $f) { $f->make()->work(); }', ['Doc\Target::work']];

        yield 'union array elements' => ['/** @param list<Target>|list<Other> $values */ function run($values) { $values[0]->work(); }', ['Doc\Other::work', 'Doc\Target::work']];

        yield 'magic property' => ['/** @property-read Target $value */ class Subject { function run() { $this->value->work(); } }', ['Doc\Target::work']];

        yield 'magic method return' => ['/** @method Target make() */ class Subject { function run() { $this->make()->work(); } }', ['Doc\Target::work']];
    }
}
