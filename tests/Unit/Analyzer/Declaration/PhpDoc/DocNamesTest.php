<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Declaration\PhpDoc;

use App\Analyzer\Declaration\PhpDoc\DocNames;
use App\Analyzer\Declaration\PhpDoc\DocParser;
use App\Analyzer\Declaration\PhpDoc\DocScope;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\NameContext;
use PhpParser\Node\Name;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DocNames::class)]
#[UsesNamespace('App\Analyzer\Declaration\PhpDoc')]
#[Small]
final class DocNamesTest extends TestCase
{
    public function testIdentifierDoesNotExpandATypeAlreadyBeingResolved(): void
    {
        $names = new NameContext(new Collecting());
        $names->startNamespace(new Name('App'));
        $doc = (new DocParser())->parse('/** @template T of Item */');
        $scope = (new DocScope($names))->withTypes($doc);

        self::assertSame(['App\Item'], DocNames::identifier('T', $scope, []));
        self::assertSame([], DocNames::identifier('T', $scope, ['T']));
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerComments')]
    public function testOfTypesContributesOnlyClassNames(string $comment, array $expected): void
    {
        $names = new NameContext(new Collecting());
        $names->startNamespace(new Name('App'));
        $doc = (new DocParser())->parse($comment);
        $scope = (new DocScope($names, 'App\Subject'))->withTypes($doc);

        self::assertSame($expected, DocNames::of($doc, $scope));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerComments(): iterable
    {
        yield 'shape labels and literals' => ['/** @return array{Fake: Item, "Ghost": \'Literal\'} */', ['App\Item']];

        yield 'callable parameters and result' => ['/** @return \Closure(Item, Other=): Item */', ['Closure', 'App\Item', 'App\Other']];

        yield 'constant owner' => ['/** @return key-of<Item::MAP> */', ['App\Item']];

        yield 'global constant' => ['/** @return PHP_VERSION_ID */', []];

        yield 'this object' => ['/** @return $this */', ['App\Subject']];

        yield 'pseudo closure' => ['/** @return pure-Closure */', ['Closure']];

        yield 'builtin types' => ['/** @return int<min, max>|non-empty-string|class-string */', []];

        yield 'range bounds are not reserved class names' => ['/** @return Min|Max|int<min, max> */', ['App\Min', 'App\Max']];

        yield 'unbounded template' => ["/**\n * @template T\n * @return T\n */", []];

        yield 'bounded template' => ["/**\n * @template T of Item\n * @return T\n */", ['App\Item']];

        yield 'recursive alias' => ["/**\n * @phpstan-type Row array{next: Row, item: Item}\n * @return Row\n */", ['App\Item']];

        yield 'malformed type' => ['/** @return array{broken */', []];

        yield 'Doctrine arguments are not type references' => ['/** @Custom(Item::class) */', []];

        yield 'callable template scope' => ['/** @param callable<T of Item>(T): T $callback */', ['App\Item']];

        yield 'method template scope' => ['/** @method T map<T of Item>(T $value) */', ['App\Item']];

        yield 'uppercase integer range' => ['/** @return INT<min, max> */', []];

        yield 'constant integer bounds' => ['/** @return int<Item::MIN, Item::MAX> */', ['App\Item']];

        yield 'constant and literal integer bounds' => ['/** @return int<0, Item::MAX> */', ['App\Item']];

        yield 'duplicates retain a sequential list' => ['/** @return array{Item, Item, Other} */', ['App\Item', 'App\Other']];

        yield 'scalar types are case insensitive' => ['/** @return INT|STRING|BOOL|FLOAT|OBJECT|MIXED */', []];

        yield 'unknown hyphenated pseudo type' => ['/** @return future-pseudo-type */', []];

        yield 'OCI class names retain their hyphen' => ['/** @return OCI-Lob */', ['App\OCI-Lob']];
    }
}
