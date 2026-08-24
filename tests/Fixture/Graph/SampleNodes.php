<?php

declare(strict_types=1);

namespace Tests\Fixture\Graph;

use App\Analyzer\Graph\Node\BuiltinNode;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\BuiltinNodeId;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;

/**
 * Symbols of a small imagined codebase, ready to assert against.
 *
 * Naming them once keeps a test that is about how a symbol is rendered or walked
 * from also being about how a symbol is built.
 */
final class SampleNodes
{
    /**
     * A class of the imagined codebase.
     *
     * @return ClassNode The node for `App\Domain\Invoice`
     */
    public static function invoice(): ClassNode
    {
        return new ClassNode(ClassNodeId::of('App\Domain\Invoice'), true);
    }

    /**
     * A method of the imagined codebase.
     *
     * @return MethodNode The node for `App\Domain\Invoice::total`
     */
    public static function total(): MethodNode
    {
        return new MethodNode(MethodNodeId::of('App\Domain\Invoice', 'total'), true);
    }

    /**
     * A type PHP resolves itself, which therefore has nothing below it.
     *
     * @return BuiltinNode The node for `int`
     */
    public static function builtin(): BuiltinNode
    {
        return new BuiltinNode(BuiltinNodeId::of('int'), true);
    }

    /**
     * A symbol outside the analyzed sources.
     *
     * @return UnknownNode The node for `App\Domain\Missing`
     */
    public static function unresolved(): UnknownNode
    {
        return new UnknownNode(new UnknownNodeId('App\Domain\Missing'));
    }
}
