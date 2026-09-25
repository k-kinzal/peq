<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer;

use App\Analyzer\CallSources;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[UsesClass(\App\Analyzer\Graph\FileMeta::class)]
#[UsesClass(\App\Analyzer\Graph\NodeId\MethodNodeId::class)]
#[UsesClass(\App\Analyzer\Graph\Node\MethodNode::class)]
#[UsesClass(\App\Analyzer\Graph\QualifiedName::class)]
#[UsesClass(\App\Analyzer\Graph\Resolution\ClassHierarchy::class)]
#[UsesClass(\App\Analyzer\SourceParser::class)]
#[\PHPUnit\Framework\Attributes\UsesNamespace('App\Analyzer\Declaration\PhpDoc')]
#[CoversClass(CallSources::class)]
#[UsesClass(\App\Analyzer\CachedSyntax::class)]
#[UsesClass(\App\Analyzer\Declaration\Calls\WrittenCalls::class)]
#[UsesClass(\App\Analyzer\Graph\Call\CallArgument::class)]
#[Small]
final class CallSourcesTest extends TestCase
{
    public function testCallableFindsTheDeclaredBodyRatherThanAnotherMethodInTheFile(): void
    {
        $file = dirname(__DIR__, 2).'/Fixture/Source/Dip.php';
        $source = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Tests\Fixture\Source\Dip\Controller', 'action'), true, new \App\Analyzer\Graph\FileMeta($file, 52, 1));

        $body = (new CallSources(80300))->callable($source);

        self::assertInstanceOf(\PhpParser\Node\Stmt\ClassMethod::class, $body);
        self::assertSame('action', $body->name->toString());
        self::assertCount(2, $body->getStmts() ?? []);
    }

    public function testFileCachesAnUnavailableFileAsHavingNoBodies(): void
    {
        self::assertSame([], (new CallSources(80300))->file('/peq-file-that-does-not-exist.php'));
    }

    public function testCallableDisambiguatesOwnersAndMethodNamesOnTheSameLine(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'peq-bodies-');
        self::assertNotFalse($file);
        file_put_contents($file, '<?php namespace App; class First { function Run() { first(); } } class Second { function otherRun() {} function Run() { second(); } }');
        $reader = new CallSources(80300);
        $source = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('App\Second', 'RUN'), true, new \App\Analyzer\Graph\FileMeta($file, 1, 1));

        $body = $reader->callable($source);
        unlink($file);

        self::assertInstanceOf(\PhpParser\Node\Stmt\ClassMethod::class, $body);
        self::assertSame('function Run()\n{\n    second();\n}', str_replace("\n", '\n', (new \PhpParser\PrettyPrinter\Standard())->prettyPrint([$body])));
        self::assertSame($body, $reader->callable($source));
    }

    public function testCallableResolvesAnExplicitTraitAliasAmongSameLineDeclarations(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'peq-trait-alias-');
        self::assertNotFalse($file);
        file_put_contents($file, '<?php trait First { function original() {} function extra() {} } trait Second { function unrelated() {} } class Subject { use First, Second { First::original as alias; } } function unrelated() {}');
        $reader = new CallSources(80300);
        $source = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Subject', 'alias'), true, new \App\Analyzer\Graph\FileMeta($file, 1, 1));
        $body = $reader->callable($source);
        unlink($file);

        self::assertInstanceOf(\PhpParser\Node\Stmt\ClassMethod::class, $body);
        self::assertSame('original', $body->name->toString());
        self::assertSame('First', $body->getAttribute('peqOwner'));
    }

    public function testCallableFallsBackToAnAliasedTraitBodyAtItsOriginalLine(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'peq-bodies-');
        self::assertNotFalse($file);
        file_put_contents($file, "<?php function unrelated() {}\ntrait Shared { function original() {} }\n");
        $reader = new CallSources(80300);
        $source = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Service', 'alias'), true, new \App\Analyzer\Graph\FileMeta($file, 2, 1));

        $body = $reader->callable($source);
        unlink($file);

        self::assertInstanceOf(\PhpParser\Node\Stmt\ClassMethod::class, $body);
        self::assertSame('original', $body->name->toString());
    }

    public function testCallableRequiresTheRecordedSourceLine(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'peq-bodies-');
        self::assertNotFalse($file);
        file_put_contents($file, "<?php\nclass Service { function run() {} }\n");
        $source = new \App\Analyzer\Graph\Node\MethodNode(\App\Analyzer\Graph\NodeId\MethodNodeId::of('Service', 'run'), true, new \App\Analyzer\Graph\FileMeta($file, 1, 1));

        $body = (new CallSources(80300))->callable($source);
        unlink($file);

        self::assertNull($body);
    }

    public function testReadFindsFunctionsAndAnonymousClassMethodsWithoutKeepingOtherSyntax(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'peq-bodies-');
        self::assertNotFalse($file);
        file_put_contents($file, '<?php namespace App; function invoke() {} $object = new class { function run() {} };');

        $bodies = (new CallSources(80300))->read($file);
        unlink($file);

        self::assertSame(['invoke', 'run'], array_map(static fn ($body): string => $body->name->toString(), $bodies));
        self::assertInstanceOf(\PhpParser\Node\Stmt\Function_::class, $bodies[0]);
        self::assertSame('App\invoke', $bodies[0]->namespacedName?->toString());
    }

    public function testReadRejectsAFileWithRecoverableSyntaxErrors(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'peq-bodies-');
        self::assertNotFalse($file);
        file_put_contents($file, '<?php function valid() {} function broken() { $value = ; }');

        $bodies = (new CallSources(80300))->read($file);
        unlink($file);

        self::assertSame([], $bodies);
    }
}
