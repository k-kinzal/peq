<?php

declare(strict_types=1);

namespace Tests\Diff;

use App\Analyzer\Declaration\PhpDoc\DocBlock;
use App\Analyzer\Declaration\PhpDoc\DocIndex;
use App\Analyzer\Declaration\PhpDoc\DocParser;
use App\Analyzer\Declaration\PhpDoc\DocScope;
use App\Analyzer\PhpStanAnalyzer\ContainerFactory;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\NameContext;
use PhpParser\Node\Name;
use PHPStan\Type\FileTypeMapper;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Uses PHPStan's resolver as an independent oracle, outside peq's shared graph pass.
 *
 * @internal
 */
#[CoversNothing]
#[Large]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PhpDocResolutionDifferenceTest extends TestCase
{
    /**
     * @param 'param'|'return'|'var'      $family
     * @param array<string, list<string>> $expected
     *
     * @throws \PHPStan\DependencyInjection\MissingServiceException
     */
    #[DataProvider('providerComments')]
    public function testSelectedObjectTypesMatchPHPStan(string $family, string $comment, array $expected): void
    {
        $file = sys_get_temp_dir().'/peq-phpdoc-oracle-'.hash('sha256', $comment).'.php';
        file_put_contents($file, '<?php namespace Doc; use Domain\Target as Item; '.$comment.' function run($value) {}');
        $container = (new ContainerFactory())->create([$file], []);
        $resolver = $container->getByType(FileTypeMapper::class);
        $doc = (new DocParser())->parse($comment);
        $resolved = $resolver->getResolvedPhpDoc($file, null, null, 'Doc\run', $comment);
        $tags = match ($family) {
            'param' => $resolved->getParamTags(),
            'var' => $resolved->getVarTags(),
            'return' => ['' => $resolved->getReturnTag()],
        };
        $reference = [];
        foreach ($tags as $name => $tag) {
            $type = $tag?->getType();
            $parts = $type instanceof \PHPStan\Type\UnionType ? $type->getTypes() : [$type];
            $reference[$name] = array_values(array_unique(array_merge([], ...array_map(static fn (?\PHPStan\Type\Type $part): array => $part?->getObjectClassNames() ?? [], $parts))));
            sort($reference[$name]);
        }
        $names = new NameContext(new Collecting());
        $names->startNamespace(new Name('Doc'));
        $names->addAlias(new Name('Domain\Target'), 'Item', \PhpParser\Node\Stmt\Use_::TYPE_NORMAL);
        $block = new DocBlock($doc, new DocScope($names));
        $actual = [];
        foreach ((new DocIndex())->types($block, $family) as $name => $value) {
            $actual[$name] = array_values(array_filter(explode('|', $value->objects()), static fn (string $name): bool => $name !== ''));
            sort($actual[$name]);
        }

        self::assertSame($expected, $reference);
        self::assertSame($reference, $actual);
    }

    /**
     * @return iterable<string, array{'param'|'return'|'var', string, array<string, list<string>>}>
     */
    public static function providerComments(): iterable
    {
        yield 'parameter priority' => ['param', "/**\n * @phpstan-param Item \$value\n * @psalm-param Other \$value\n * @phan-param object \$value\n * @param mixed \$value\n */", ['value' => ['Domain\Target']]];

        yield 'variable priority' => ['var', "/**\n * @phpstan-var Item \$value\n * @var Other \$value\n */", ['value' => ['Domain\Target']]];

        yield 'return priority' => ['return', "/**\n * @phpstan-return Item\n * @return Other\n */", ['' => ['Domain\Target']]];

        yield 'ordinary parameter' => ['param', '/** @param Item $value */', ['value' => ['Domain\Target']]];

        yield 'psalm parameter' => ['param', '/** @psalm-param Item $value */', ['value' => ['Domain\Target']]];

        yield 'phan parameter' => ['param', '/** @phan-param Item $value */', ['value' => ['Domain\Target']]];

        yield 'list is not an object' => ['param', '/** @param list<Item> $value */', ['value' => []]];

        yield 'array is not an object' => ['param', '/** @param Item[] $value */', ['value' => []]];

        yield 'class string is not an object' => ['param', '/** @param class-string<Item> $value */', ['value' => []]];

        yield 'union' => ['param', '/** @param Item|Other|null $value */', ['value' => ['Doc\Other', 'Domain\Target']]];
    }
}
