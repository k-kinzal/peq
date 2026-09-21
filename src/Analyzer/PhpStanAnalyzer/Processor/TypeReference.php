<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer\Processor;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;

/**
 * One class-like type named in a written type, and where it was written.
 *
 * A declared type may name several types — a union, an intersection — and each of
 * them sits at its own position in the source. Pairing the node with its position
 * lets a caller build the relation its own declaration calls for, whether that is a
 * return type, a parameter type or a property type, without each of them repeating
 * how a written type is read.
 *
 * @visibility parent
 */
final readonly class TypeReference
{
    /**
     * @param ClassNode $node The class-like the type names
     * @param FileMeta  $meta Where in the source the name was written
     */
    public function __construct(
        public ClassNode $node,
        public FileMeta $meta,
    ) {}
}
