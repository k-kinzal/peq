<?php

declare(strict_types=1);

namespace Tests\Fixture\Graph;

use App\Analyzer\Graph\Edge\Declaration\MethodEdge;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;

/**
 * Relations of a small imagined codebase, ready to assert against.
 *
 * Building a real edge means building two real nodes and a location, and the tests
 * that read an edge back are not about any of that. Naming the arrangement once
 * keeps those tests down to the one thing each of them is checking.
 */
final class SampleEdges
{
    /**
     * A method of one class calling a method of another.
     *
     * @return MethodCallEdge The relation `App\Domain\Invoice::total` -> `App\Domain\Money::add`
     */
    public static function methodCall(): MethodCallEdge
    {
        $meta = self::meta();

        return new MethodCallEdge(
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
            new MethodNode(MethodNodeId::of('App\Domain\Money', 'add'), true, $meta),
            $meta,
        );
    }

    /**
     * A class declaring one method.
     *
     * @return MethodEdge The relation `App\Domain\Invoice` -> `App\Domain\Invoice::total`
     */
    public static function methodDeclaration(): MethodEdge
    {
        $meta = self::meta();

        return new MethodEdge(
            new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true, $meta),
            new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true, $meta),
            $meta,
        );
    }

    /**
     * The source location every sample relation is written at.
     *
     * @return FileMeta A location in an imagined invoice file
     */
    public static function meta(): FileMeta
    {
        return new FileMeta('/project/src/Domain/Invoice.php', 12, 1);
    }
}
