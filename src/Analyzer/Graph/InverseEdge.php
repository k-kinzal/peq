<?php

declare(strict_types=1);

namespace App\Analyzer\Graph;

/**
 * An edge that exists only as the opposite reading of another edge.
 *
 * Source code writes a relation in one direction: a method calls another method,
 * a class declares a property. The graph makes the opposite reading available too,
 * so that "what depends on this symbol" is one adjacency lookup rather than a scan
 * of the whole graph. Those opposite readings are inverse edges.
 *
 * An inverse edge is derived, never authored: it carries the edge it was derived
 * from, so inverting it back is an identity rather than a reconstruction. Because
 * twenty kinds of relation collapse into the two inverse kinds (UsedBy and
 * DeclaredIn), a reconstruction could only guess the original kind from the node
 * types involved — which is why the origin is kept instead.
 *
 * Graph::merge() uses this interface to keep derived edges out of a merge: they
 * are regenerated from the edges they belong to.
 */
interface InverseEdge extends Edge
{
    /**
     * Returns the edge this inverse edge was derived from.
     *
     * @return Edge The edge whose opposite reading this edge is
     */
    public function invert(): Edge;
}
