<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge\Inverse;

use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\EdgeKind;
use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\InverseEdge;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;
use Override;

/**
 * The opposite reading of a usage relation: "is used by".
 *
 * Where a usage edge says "this method calls that method", this edge says "that
 * method is called by this one". It is derived from the usage edge it carries and
 * inverts straight back into it, so no usage kind is lost by making the reverse
 * direction available.
 */
final readonly class UsedByEdge implements InverseEdge
{
    /**
     * @param Edge $usage The usage relation this edge is the opposite reading of
     */
    public function __construct(
        private Edge $usage,
    ) {}

    /**
     * Returns the node that is used, which the usage edge points at.
     *
     * @return NodeId<Node> The used node identifier
     */
    #[Override]
    public function from(): NodeId
    {
        return $this->usage->to();
    }

    /**
     * Returns the node that uses it, which the usage edge starts at.
     *
     * @return NodeId<Node> The using node identifier
     */
    #[Override]
    public function to(): NodeId
    {
        return $this->usage->from();
    }

    /**
     * Returns the kind that marks this edge as a reverse usage relation.
     *
     * @return EdgeKind Always EdgeKind::UsedBy
     */
    #[Override]
    public function kind(): EdgeKind
    {
        return EdgeKind::UsedBy;
    }

    /**
     * Returns where the underlying usage is written in the source code.
     *
     * @return FileMeta The location of the usage this edge reverses
     */
    #[Override]
    public function meta(): FileMeta
    {
        return $this->usage->meta();
    }

    /**
     * Returns the usage relation this edge was derived from.
     *
     * @example The kind of the original relation survives the reverse reading
     *     $meta = new \App\Analyzer\Graph\FileMeta('/project/src/Invoice.php', 12, 1);
     *     $caller = new \App\Analyzer\Graph\Node\MethodNode(
     *         \App\Analyzer\Graph\NodeId\MethodNodeId::of('App\\Domain\\Invoice', 'total'), true, $meta);
     *     $called = new \App\Analyzer\Graph\Node\MethodNode(
     *         \App\Analyzer\Graph\NodeId\MethodNodeId::of('App\\Domain\\Money', 'add'), true, $meta);
     *     $usage = new \App\Analyzer\Graph\Edge\Usage\MethodCallEdge($caller, $called, $meta);
     *     (new \App\Analyzer\Graph\Edge\Inverse\UsedByEdge($usage))->invert()->kind() // => \App\Analyzer\Graph\EdgeKind::MethodCall
     *
     * @return Edge The original usage edge, with its original kind intact
     */
    #[Override]
    public function invert(): Edge
    {
        return $this->usage;
    }
}
