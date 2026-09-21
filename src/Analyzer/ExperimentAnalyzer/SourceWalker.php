<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer;

use App\Analyzer\ExperimentAnalyzer\Emitter\CallableEmitter;
use App\Analyzer\ExperimentAnalyzer\Emitter\DeclarationEmitter;
use App\Analyzer\ExperimentAnalyzer\Emitter\UsageEmitter;
use App\Analyzer\Graph\NodeKind;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Trait_;

/**
 * Walks a file outside of any class body.
 *
 * Everything a file can write that is not a class member is read here: the
 * declarations at its top level, the ones written inside an `if` or a function, the
 * anonymous classes written inside an expression, and the relations written in the
 * body of a function. What a class body holds is ClassWalker's work, and what a
 * method body reaches is BodyWalker's.
 *
 * A trait declaration is met here and recorded, but its members are not read: a
 * trait's methods belong to the classes that use it, and they are read once per
 * using class instead of once at the trait.
 *
 * @visibility namespace
 */
final class SourceWalker
{
    /**
     * The walk that reads what a class body holds.
     */
    private readonly ClassWalker $classWalker;

    /**
     * @param SourceIndex   $index    What the analysed files declare
     * @param GraphRecorder $recorder Where what the walk finds is collected
     */
    public function __construct(
        private readonly SourceIndex $index,
        private readonly GraphRecorder $recorder,
    ) {
        $this->classWalker = new ClassWalker($index, $recorder, $this);
    }

    /**
     * Walks one analysed file from its top.
     *
     * @param ParsedSource $source The parsed file
     */
    public function walkFile(ParsedSource $source): void
    {
        foreach ($source->statements as $statement) {
            $this->walk($statement, AnalysisScope::inFile($this->index, $source->path), $source);
        }
    }

    /**
     * Walks one node and everything written inside it.
     *
     * A declaration met on the way is recorded and taken over by the walk that knows
     * how to read it; anything else is descended into. Relations are recorded only
     * where a symbol exists to attribute them to, which outside a class body means
     * the body of a function.
     *
     * @param PhpParserNode $node   The node to walk
     * @param AnalysisScope $scope  Where in the sources it is written
     * @param ParsedSource  $source The file being walked
     */
    public function walk(PhpParserNode $node, AnalysisScope $scope, ParsedSource $source): void
    {
        if ($node instanceof ClassLike) {
            $this->walkClassLike($node, $scope, $source);

            return;
        }

        if ($node instanceof Function_) {
            $this->recorder->record(CallableEmitter::globalFunction($node, $scope));
            $inside = $node->namespacedName === null ? $scope : $scope->enteringFunction($node->namespacedName->toString());
            $this->descend($node, $inside, $source);

            return;
        }

        if ($scope->className === null && $scope->functionName !== null && UsageEmitter::records($node)) {
            $this->recorder->record(UsageEmitter::emit($node, $scope));
        }

        $this->descend($node, $scope, $source);
    }

    /**
     * Walks everything written inside a node.
     *
     * @param PhpParserNode $node   The node whose contents to walk
     * @param AnalysisScope $scope  Where in the sources it is written
     * @param ParsedSource  $source The file being walked
     */
    public function descend(PhpParserNode $node, AnalysisScope $scope, ParsedSource $source): void
    {
        $subNodes = get_object_vars($node);
        foreach ($node->getSubNodeNames() as $name) {
            $child = $subNodes[$name] ?? null;
            if ($child instanceof PhpParserNode) {
                $this->walk($child, $scope, $source);

                continue;
            }
            if (!is_array($child)) {
                continue;
            }
            foreach ($child as $item) {
                if ($item instanceof PhpParserNode) {
                    $this->walk($item, $scope, $source);
                }
            }
        }
    }

    /**
     * Walks a class-like declaration met while walking a file.
     *
     * @param ClassLike     $node   The declaration met while walking
     * @param AnalysisScope $scope  Where in the sources it is written
     * @param ParsedSource  $source The file being walked
     */
    public function walkClassLike(ClassLike $node, AnalysisScope $scope, ParsedSource $source): void
    {
        $kind = ClassLikeDeclaration::kindOf($node);
        if ($kind === null) {
            return;
        }

        if ($node->namespacedName === null) {
            if (!$node instanceof Class_ || $node->name !== null) {
                return;
            }
            $name = $source->anonymous->nameOf($node, $this->index->relativePathOf($scope->file));
            $this->classWalker->walk($node, NodeKind::Klass, $name, $scope, $source);

            return;
        }

        $name = $node->namespacedName->toString();
        $this->recorder->record(DeclarationEmitter::emit($node, $kind, $name, $scope));

        if ($node instanceof Trait_) {
            return;
        }

        $this->classWalker->walk($node, $kind, $name, $scope, $source);
    }
}
