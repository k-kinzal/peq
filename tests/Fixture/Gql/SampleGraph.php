<?php

declare(strict_types=1);

namespace Tests\Fixture\Gql;

use App\Analyzer\Graph\Declaration\AttributeUsage;
use App\Analyzer\Graph\Declaration\Modifiers;
use App\Analyzer\Graph\Declaration\Parameter;
use App\Analyzer\Graph\Declaration\Signature;
use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Declaration\Visibility;
use App\Analyzer\Graph\Edge\Declaration\ExtendsEdge;
use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;

/**
 * A small codebase, analysed, for tests about querying one.
 *
 * Every test about the query engine needs a graph to run against, and a graph built
 * inside a test method is a dozen lines nobody reads before the two that matter. This
 * one is shaped like the codebases peq is asked about: a controller reaching a domain
 * object reaching a cache, a base class, a route attribute, and one symbol analysis
 * only ever saw referred to.
 *
 * It is small enough to reason about by hand — eight symbols, eight relations — and
 * varied enough that a test can ask about labels, families, properties, directions,
 * lengths and unresolved symbols without building anything of its own.
 *
 * `App\Http\Controller` extends `App\Http\Kernel` and declares `::show` and `::store`.
 * `App\Domain\Invoice` declares `::total` and `App\Cache\Store` declares `::get`.
 * Both controller methods call `App\Domain\Invoice::total`, which calls
 * `App\Cache\Store::get`, so the cache is two calls away from a request and three
 * relations away from the controller itself.
 */
final class SampleGraph
{
    /**
     * Returns the codebase as peq analyses it.
     *
     * @return Graph The analysed graph
     */
    public static function analysed(): Graph
    {
        $graph = new Graph();
        $graph->addNodes([
            self::controller(),
            self::kernel(),
            self::invoice(),
            self::cache(),
            self::show(),
            self::store(),
            self::total(),
            self::get(),
            new UnknownNode(new UnknownNodeId('App\Missing')),
        ]);
        $graph->addEdge(new ExtendsEdge(self::controller(), self::kernel(), new FileMeta('/project/src/Http/Controller.php', 10, 5)));
        $graph->addEdge(new MethodEdge(self::controller(), self::show(), new FileMeta('/project/src/Http/Controller.php', 20, 5)));
        $graph->addEdge(new MethodEdge(self::controller(), self::store(), new FileMeta('/project/src/Http/Controller.php', 30, 5)));
        $graph->addEdge(new MethodEdge(self::invoice(), self::total(), new FileMeta('/project/src/Domain/Invoice.php', 12, 5)));
        $graph->addEdge(new MethodEdge(self::cache(), self::get(), new FileMeta('/project/src/Cache/Store.php', 8, 5)));
        $graph->addEdge(new MethodCallEdge(self::show(), self::total(), new FileMeta('/project/src/Http/Controller.php', 22, 5)));
        $graph->addEdge(new MethodCallEdge(self::store(), self::total(), new FileMeta('/project/src/Http/Controller.php', 32, 5)));
        $graph->addEdge(new MethodCallEdge(self::total(), self::get(), new FileMeta('/project/src/Domain/Invoice.php', 14, 5)));

        return $graph;
    }

    /**
     * Returns three methods that call round in a ring.
     *
     * The sample codebase has no cycle in it, which is the shape most code is and the
     * wrong shape for one question: what a path mode forbids. `TRAIL`, `SIMPLE` and
     * `ACYCLIC` all agree about a graph nothing loops in, so a test that only ever
     * looked at that one would pass whichever rule it was really checking.
     *
     * Mutual recursion is ordinary in source code, so this is ordinary too: three
     * methods, each calling the next, the last calling the first — and the last
     * calling back into the middle as well, which is what lets a path meet a symbol
     * twice without crossing a relation twice, the one case that tells `TRAIL` and
     * `SIMPLE` apart.
     *
     * @return Graph The analysed graph
     */
    public static function recursive(): Graph
    {
        $first = new MethodNode(MethodNodeId::of('App\Ring\Round', 'first'), true, new FileMeta('/project/src/Ring/Round.php', 5, 5));
        $second = new MethodNode(MethodNodeId::of('App\Ring\Round', 'second'), true, new FileMeta('/project/src/Ring/Round.php', 10, 5));
        $third = new MethodNode(MethodNodeId::of('App\Ring\Round', 'third'), true, new FileMeta('/project/src/Ring/Round.php', 15, 5));

        $graph = new Graph();
        $graph->addNodes([$first, $second, $third]);
        $graph->addEdge(new MethodCallEdge($first, $second, new FileMeta('/project/src/Ring/Round.php', 6, 5)));
        $graph->addEdge(new MethodCallEdge($second, $third, new FileMeta('/project/src/Ring/Round.php', 11, 5)));
        $graph->addEdge(new MethodCallEdge($third, $first, new FileMeta('/project/src/Ring/Round.php', 16, 5)));
        $graph->addEdge(new MethodCallEdge($third, $second, new FileMeta('/project/src/Ring/Round.php', 17, 5)));

        return $graph;
    }

    /**
     * Returns the controller the sample codebase is entered through.
     *
     * @return ClassNode The symbol
     */
    public static function controller(): ClassNode
    {
        return new ClassNode(
            ClassNodeId::of('App\Http\Controller'),
            true,
            new FileMeta('/project/src/Http/Controller.php', 10, 5),
            new SymbolDeclaration(modifiers: new Modifiers(final: true)),
        );
    }

    /**
     * Returns the class the controller extends.
     *
     * @return ClassNode The symbol
     */
    public static function kernel(): ClassNode
    {
        return new ClassNode(ClassNodeId::of('App\Http\Kernel'), true, new FileMeta('/project/src/Http/Kernel.php', 7, 5));
    }

    /**
     * Returns the domain object the controller reaches.
     *
     * @return ClassNode The symbol
     */
    public static function invoice(): ClassNode
    {
        return new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, new FileMeta('/project/src/Domain/Invoice.php', 5, 5));
    }

    /**
     * Returns the cache the domain object reaches.
     *
     * @return ClassNode The symbol
     */
    public static function cache(): ClassNode
    {
        return new ClassNode(ClassNodeId::of('App\Cache\Store'), true, new FileMeta('/project/src/Cache/Store.php', 3, 5));
    }

    /**
     * Returns the routed method a request arrives at.
     *
     * @return MethodNode The symbol
     */
    public static function show(): MethodNode
    {
        return new MethodNode(
            MethodNodeId::of('App\Http\Controller', 'show'),
            true,
            new FileMeta('/project/src/Http/Controller.php', 20, 5),
            new SymbolDeclaration(
                visibility: Visibility::Public,
                signature: new Signature([new Parameter('id', 'int')], 'string'),
                attributes: [new AttributeUsage('App\Http\Route', ["'/invoices/{id}'"])],
            ),
        );
    }

    /**
     * Returns the method that writes rather than reads.
     *
     * @return MethodNode The symbol
     */
    public static function store(): MethodNode
    {
        return new MethodNode(
            MethodNodeId::of('App\Http\Controller', 'store'),
            true,
            new FileMeta('/project/src/Http/Controller.php', 30, 5),
            new SymbolDeclaration(visibility: Visibility::Public, signature: new Signature([], 'void')),
        );
    }

    /**
     * Returns the domain method both controller methods reach.
     *
     * @return MethodNode The symbol
     */
    public static function total(): MethodNode
    {
        return new MethodNode(
            MethodNodeId::of('App\Domain\Invoice', 'total'),
            true,
            new FileMeta('/project/src/Domain/Invoice.php', 12, 5),
            new SymbolDeclaration(
                visibility: Visibility::Public,
                modifiers: new Modifiers(static: true),
                signature: new Signature([], 'int'),
                deprecated: true,
            ),
        );
    }

    /**
     * Returns the cache method the domain method reaches.
     *
     * @return MethodNode The symbol
     */
    public static function get(): MethodNode
    {
        return new MethodNode(
            MethodNodeId::of('App\Cache\Store', 'get'),
            true,
            new FileMeta('/project/src/Cache/Store.php', 8, 5),
            new SymbolDeclaration(
                visibility: Visibility::Protected,
                signature: new Signature([new Parameter('key', 'string')], 'string'),
            ),
        );
    }
}
