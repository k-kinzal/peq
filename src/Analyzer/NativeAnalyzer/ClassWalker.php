<?php

declare(strict_types=1);

namespace App\Analyzer\NativeAnalyzer;

use App\Analyzer\Graph\NodeKind;
use App\Analyzer\NativeAnalyzer\Emitter\CallableEmitter;
use App\Analyzer\NativeAnalyzer\Emitter\MemberEmitter;
use PhpParser\Modifiers;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\TraitUse;

/**
 * Walks the body of a class, an interface or an enum.
 *
 * What a class-like body declares is read here, in the order the reference engine
 * reads it — static methods first, the constructor next, properties last — because
 * that order decides which of two descriptions of the same symbol the graph keeps,
 * and a graph that kept the other one would not be the same graph.
 *
 * The statements a class takes on from a trait are read here too, once per using
 * class and as if they had been written in that class. Which of them are taken on at
 * all is not a question about the trait: it is PHP's own resolution, and
 * TraitFlattening answers it.
 *
 * @visibility namespace
 */
final class ClassWalker
{
    /**
     * The walk that reads what a method body reaches out to.
     */
    private readonly BodyWalker $bodies;

    /**
     * @param SourceIndex   $index    What the analysed files declare
     * @param GraphRecorder $recorder Where what the walk finds is collected
     * @param SourceWalker  $walker   The walk to hand anything that is not a class member back to
     */
    public function __construct(
        private readonly SourceIndex $index,
        private readonly GraphRecorder $recorder,
        private readonly SourceWalker $walker,
    ) {
        $this->bodies = new BodyWalker();
    }

    /**
     * Walks a class-like body.
     *
     * @param ClassLike     $node   The declaration whose body to walk
     * @param NodeKind      $kind   Which of the four kinds of declaration it is
     * @param string        $name   The name analysis knows it by
     * @param AnalysisScope $outer  Where the declaration itself is written
     * @param ParsedSource  $source The file being walked
     */
    public function walk(ClassLike $node, NodeKind $kind, string $name, AnalysisScope $outer, ParsedSource $source): void
    {
        $parent = $node instanceof Class_ && $node->extends !== null ? $node->extends->toString() : null;
        $scope = $outer->enteringClass($name, $parent !== null && $this->index->knowsClass($parent) ? $parent : null);

        foreach (self::inReadingOrder($node->stmts) as $statement) {
            $this->member($statement, $node, $kind, $scope, $source);
        }
    }

    /**
     * Puts the statements of a class-like into the order they are read in.
     *
     * @param array<Stmt> $statements The statements as they are written
     *
     * @return list<Stmt> The same statements, in reading order
     */
    public static function inReadingOrder(array $statements): array
    {
        usort($statements, static function (Stmt $first, Stmt $second): int {
            if ($first instanceof Property) {
                return 1;
            }
            if ($second instanceof Property) {
                return -1;
            }
            if (!$first instanceof ClassMethod || !$second instanceof ClassMethod) {
                return 0;
            }

            return [($first->flags & Modifiers::STATIC) === 0, strtolower($first->name->toString()) !== '__construct']
                <=> [($second->flags & Modifiers::STATIC) === 0, strtolower($second->name->toString()) !== '__construct'];
        });

        return $statements;
    }

    /**
     * Reads one statement of a class-like body.
     *
     * @param Stmt          $statement The statement to read
     * @param ClassLike     $class     The declaration it is read for, which is the using class for a trait statement
     * @param NodeKind      $kind      The kind of that declaration
     * @param AnalysisScope $scope     Where in the sources the walk stands
     * @param ParsedSource  $source    The file being walked
     */
    public function member(Stmt $statement, ClassLike $class, NodeKind $kind, AnalysisScope $scope, ParsedSource $source): void
    {
        if ($statement instanceof ClassMethod) {
            $this->method($statement, $class, $kind, $scope, $source);

            return;
        }
        if ($statement instanceof Property) {
            $this->recorder->record(MemberEmitter::properties($statement, $scope));

            return;
        }
        if ($statement instanceof ClassConst) {
            $this->recorder->record(MemberEmitter::constants($statement, $scope));

            return;
        }
        if ($statement instanceof EnumCase) {
            $this->recorder->record(MemberEmitter::enumCase($statement, $scope));

            return;
        }
        if ($statement instanceof TraitUse) {
            $this->traitUse($statement, $class, $kind, $scope);

            return;
        }

        $this->walker->descend($statement, $scope, $source);
    }

    /**
     * Reads one method declaration and its body.
     *
     * The relations a body writes are read from the file the class is written in
     * rather than from the statements held here, because a method reached through a
     * trait is written in another file entirely and the class's own file holds no
     * body for it.
     *
     * @param ClassMethod   $node   The method declaration
     * @param ClassLike     $class  The declaration it is read for
     * @param NodeKind      $kind   The kind of that declaration
     * @param AnalysisScope $scope  Where in the sources the walk stands
     * @param ParsedSource  $source The file being walked
     */
    public function method(ClassMethod $node, ClassLike $class, NodeKind $kind, AnalysisScope $scope, ParsedSource $source): void
    {
        $methodName = $node->name->toString();
        $reading = $scope->readingTrait();
        if ($reading !== null && !TraitFlattening::keepsMethod($class, $node, $methodName, $reading, $this->index)) {
            return;
        }

        $this->recorder->record(CallableEmitter::method($node, $scope, $kind));

        $inside = $scope->enteringMethod($methodName);
        foreach ($node->params as $param) {
            $this->recorder->record(CallableEmitter::promotedProperty($param, $scope));
            $this->walker->descend($param, $scope, $source);
        }

        $written = $this->index->sourceOf($scope->file);
        $body = $scope->className === null || $written === null ? null : $written->methodBody($scope->className, $methodName);
        if ($body !== null) {
            $this->recorder->record($this->bodies->relations($body, $inside));
        }

        foreach ($node->stmts ?? [] as $statement) {
            $this->walker->walk($statement, $inside, $source);
        }
    }

    /**
     * Reads the statements a class takes on from the traits one `use` names.
     *
     * A trait is followed only into a file the analysis was asked to read, and only
     * when it is not already being read further up: a trait that uses a trait that
     * uses it back is a cycle PHP rejects, and analysis has no reason to follow it.
     *
     * @param TraitUse      $node  The `use` statement met in the class body
     * @param ClassLike     $class The declaration it is written in
     * @param NodeKind      $kind  The kind of that declaration
     * @param AnalysisScope $scope Where in the sources the walk stands
     */
    public function traitUse(TraitUse $node, ClassLike $class, NodeKind $kind, AnalysisScope $scope): void
    {
        foreach ($node->traits as $trait) {
            $traitName = $trait->toString();
            $declaration = $this->index->classLike($traitName);
            if ($declaration === null || $declaration->kind !== NodeKind::Trait || $scope->isReading($declaration->name)) {
                continue;
            }

            $renames = TraitFlattening::renames($node, $traitName);
            $inside = $scope->enteringTrait($declaration->name);
            foreach ($declaration->node->stmts as $statement) {
                $this->member(self::renamed($statement, $renames, $node, $traitName), $class, $kind, $inside, $declaration->source);
            }
        }
    }

    /**
     * Returns a method statement under the name the using class takes it on as.
     *
     * A method renamed by an `as` is taken on under the new name and only under it,
     * so the statement is read as if that were the name it was written with.
     *
     * @param Stmt                  $statement The statement as the trait writes it
     * @param array<string, string> $renames   The new name of each renamed method
     *
     * @return Stmt The statement under the name the class takes it on as
     */
    public static function renamed(Stmt $statement, array $renames, ?TraitUse $use = null, ?string $trait = null): Stmt
    {
        if (!$statement instanceof ClassMethod) {
            return $statement;
        }
        $newName = $renames[strtolower($statement->name->toString())] ?? null;
        if ($newName === null && ($use === null || $use->adaptations === [])) {
            return $statement;
        }
        $renamedMethod = clone $statement;
        if ($newName !== null) {
            $renamedMethod->name = new PhpParserNode\Identifier($newName, $statement->name->getAttributes());
        }
        foreach ($use->adaptations ?? [] as $adaptation) {
            if ($adaptation instanceof Stmt\TraitUseAdaptation\Alias
                && $adaptation->newModifier !== null
                && strtolower($adaptation->method->toString()) === strtolower($statement->name->toString())
                && ($adaptation->trait === null || strtolower($adaptation->trait->toString()) === strtolower($trait ?? ''))
            ) {
                $renamedMethod->flags = ($renamedMethod->flags & ~Modifiers::VISIBILITY_MASK) | $adaptation->newModifier;
            }
        }

        return $renamedMethod;
    }
}
