<?php

declare(strict_types=1);

namespace App\Analyzer\NativeAnalyzer;

use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;

/**
 * The names analysis gives the anonymous classes of one file.
 *
 * An anonymous class has no name in the source, yet its methods are symbols the graph
 * has to be able to name, so analysis invents one. The invented name is a hash of
 * where the class stands: the file path relative to the directory the analysis runs
 * in, the line, and — only when a line carries more than one of them — which one of
 * that line it is.
 *
 * That the name depends on the directory the analysis runs in is not a choice made
 * here. It is what the reference engine does, and a graph that named these classes
 * differently would not be the same graph.
 *
 * @visibility namespace
 */
final class AnonymousClassNaming
{
    /**
     * @param array<int, int> $indexes The position among the classes of its line, for each class on a shared line
     */
    public function __construct(
        private readonly array $indexes,
    ) {}

    /**
     * Numbers the anonymous classes a parsed file writes.
     *
     * Classes are numbered in the order they are written, so that two on one line are
     * told apart by their position among the classes of that line and not by anything
     * about their contents.
     *
     * @param list<PhpParserNode\Stmt> $statements The parsed statements of the file
     *
     * @return self The numbering of that file's anonymous classes
     */
    public static function of(array $statements): self
    {
        $perLine = [];
        foreach ((new NodeFinder())->find($statements, self::isAnonymous(...)) as $class) {
            assert($class instanceof Class_);
            $perLine[$class->getStartLine()][] = $class;
        }

        $indexes = [];
        foreach ($perLine as $classes) {
            if (count($classes) === 1) {
                continue;
            }
            foreach ($classes as $position => $class) {
                $indexes[spl_object_id($class)] = $position + 1;
            }
        }

        return new self($indexes);
    }

    /**
     * Reports whether a node is an anonymous class declaration.
     *
     * @param PhpParserNode $node A node met while walking a file
     *
     * @return bool True when the node declares a class with no name
     */
    public static function isAnonymous(PhpParserNode $node): bool
    {
        return $node instanceof Class_ && $node->name === null;
    }

    /**
     * Returns the name analysis knows one anonymous class by.
     *
     * @param Class_ $class        The anonymous class declaration
     * @param string $relativePath The file it is written in, relative to the analysis directory
     *
     * @return string The invented name
     */
    public function nameOf(Class_ $class, string $relativePath): string
    {
        $index = $this->indexes[spl_object_id($class)] ?? null;
        $position = $index === null ? '' : ':'.$index;

        return 'AnonymousClass'.md5($relativePath.':'.$class->getStartLine().$position);
    }
}
