<?php

declare(strict_types=1);

namespace App\Analyzer;

use App\Analyzer\Declaration\PhpDoc\DocExpression;
use App\Analyzer\Declaration\PhpDoc\DocIndex;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeKind;
use App\Analyzer\Graph\Resolution\ClassHierarchy;
use App\Analyzer\Graph\Resolution\TypeConstraint;
use PhpParser\Node as Syntax;
use PhpParser\Node\Expr;

/**
 * Resolves receivers from declared types, without executing application code.
 */
final class ReceiverBinding
{
    /**
     * @var array<string, string>
     */
    private array $variables = [];

    /** @var array<string, DocExpression> */
    private array $documented = [];

    /**
     * @var array<int, DocExpression>
     */
    private array $annotatedAssignments = [];
    private readonly ?string $owner;

    /**
     * Seeds receiver types from the callable signature and lexical owner.
     */
    public function __construct(private readonly ClassHierarchy $hierarchy, Node $source, private readonly DocIndex $docs = new DocIndex(), ?Syntax $syntax = null)
    {
        $this->owner = ClassHierarchy::owner($source);
        foreach ($source->declaration()?->signature->parameters ?? [] as $parameter) {
            $this->variables[$parameter->name] = $parameter->type ?? '';
        }
        $this->documented = $this->docs->types($syntax === null ? null : DocIndex::block($syntax), 'param');
    }

    /**
     * Finds the declared object type of an expression, or an empty string when unknown.
     */
    public function of(Expr $expression, int $depth = 0): string
    {
        if ($depth > 16) {
            return '';
        }
        $member = self::isMember($expression);
        $documented = $member ? null : $this->documentedType($expression, $depth);
        if ($documented !== null) {
            return $documented->objects();
        }

        return match (true) {
            $expression instanceof Expr\Variable && is_string($expression->name) => $expression->name === 'this' ? ($this->owner ?? '') : ($this->variables[$expression->name] ?? ''),
            $expression instanceof Expr\New_ && $expression->class instanceof Syntax\Name => $this->name($expression->class),
            $expression instanceof Expr\PropertyFetch, $expression instanceof Expr\NullsafePropertyFetch => $this->memberType($expression, false, $depth),
            $expression instanceof Expr\MethodCall, $expression instanceof Expr\NullsafeMethodCall => $this->memberType($expression, true, $depth),
            $expression instanceof Expr\FuncCall && $expression->name instanceof Syntax\Name => $this->hierarchy->node($expression->name->toString())?->declaration()?->signature->returnType ?? '',
            $expression instanceof Expr\Ternary => $this->of($expression->if ?? $expression->cond, $depth + 1).'|'.$this->of($expression->else, $depth + 1),
            $expression instanceof Expr\BinaryOp\Coalesce => $this->of($expression->left, $depth + 1).'|'.$this->of($expression->right, $depth + 1),
            default => '',
        };
    }

    /**
     * Keeps all known assignments: this is a conservative, flow-insensitive type set.
     */
    public function assign(Expr\Assign $assignment): void
    {
        if (!$assignment->var instanceof Expr\Variable || !is_string($assignment->var->name)) {
            return;
        }
        $name = $assignment->var->name;
        $documented = $this->annotatedAssignments[spl_object_id($assignment)] ?? $this->documentedType($assignment->expr, 0);
        if ($documented !== null) {
            $this->documented[$name] = $documented;
        }
        $known = $this->variables[$name] ?? '';
        $type = $this->of($assignment->expr);
        $this->variables[$name] = implode('|', array_unique(array_filter([$known, $type], static fn (string $part): bool => $part !== '')));
    }

    /**
     * Applies statement-local variable annotations and foreach element types.
     */
    public function annotate(Syntax $node): void
    {
        if ($node instanceof Syntax\Stmt\Foreach_ && $node->valueVar instanceof Expr\Variable && is_string($node->valueVar->name)) {
            $element = $this->documentedType($node->expr, 0)?->element();
            if ($element !== null) {
                $this->documented[$node->valueVar->name] = $element;
            }
        }
        $variables = $this->docs->types(DocIndex::block($node), 'var');
        $expression = $node instanceof Syntax\Stmt\Expression ? $node->expr : $node;
        foreach ($variables as $name => $type) {
            if ($name === '') {
                $variable = $expression instanceof Expr\Assign ? $expression->var : null;
                if (!$variable instanceof Expr\Variable || !is_string($variable->name)) {
                    continue;
                }
                $name = $variable->name;
            }
            $this->documented[$name] = $type;
            if ($expression instanceof Expr\Assign && $expression->var instanceof Expr\Variable && $expression->var->name === $name) {
                $this->annotatedAssignments[spl_object_id($expression)] = $type;
            }
        }
    }

    /**
     * Keeps containers distinct from their elements and follows documented returns.
     */
    public function documentedType(Expr $expression, int $depth): ?DocExpression
    {
        if ($depth > 16) {
            return null;
        }
        if ($expression instanceof Expr\Variable && is_string($expression->name)) {
            return $this->documented[$expression->name] ?? null;
        }
        if ($expression instanceof Expr\ArrayDimFetch) {
            $key = $expression->dim instanceof Syntax\Scalar\String_ || $expression->dim instanceof Syntax\Scalar\Int_ ? (string) $expression->dim->value : null;

            return $this->documentedType($expression->var, $depth + 1)?->element($key);
        }
        if ($expression instanceof Expr\FuncCall && $expression->name instanceof Syntax\Name) {
            $namespaced = $expression->name->getAttribute('namespacedName');

            return ($namespaced instanceof Syntax\Name ? $this->docs->returned($namespaced->toString()) : null) ?? $this->docs->returned($expression->name->toString());
        }
        if ($expression instanceof Expr\StaticCall && $expression->class instanceof Syntax\Name && $expression->name instanceof Syntax\Identifier) {
            return $this->docs->member($this->name($expression->class), $expression->name->toString(), true, $this->hierarchy);
        }
        if (self::isMember($expression) && $expression->name instanceof Syntax\Identifier) {
            $method = $expression instanceof Expr\MethodCall || $expression instanceof Expr\NullsafeMethodCall;
            $alternatives = [];
            foreach (TypeConstraint::of($this->of($expression->var, $depth + 1))->names() as $owner) {
                $type = $this->docs->member($owner, $expression->name->toString(), $method, $this->hierarchy);
                if ($type !== null) {
                    $alternatives[] = $type;
                }
            }

            return DocExpression::union($alternatives);
        }

        return null;
    }

    /**
     * Identifies the receiver expressions that share named-member lookup.
     *
     * @phpstan-assert-if-true Expr\PropertyFetch|Expr\NullsafePropertyFetch|Expr\MethodCall|Expr\NullsafeMethodCall $expression
     */
    public static function isMember(Expr $expression): bool
    {
        return $expression instanceof Expr\PropertyFetch || $expression instanceof Expr\NullsafePropertyFetch
            || $expression instanceof Expr\MethodCall || $expression instanceof Expr\NullsafeMethodCall;
    }

    /**
     * Resolves a named class in the current lexical scope.
     */
    public function name(Syntax\Name $name): string
    {
        return in_array(strtolower($name->toString()), ['self', 'static'], true) ? ($this->owner ?? '') : $name->toString();
    }

    /**
     * Reads the type declared for an accessed property or returned by a called method.
     */
    public function memberType(Expr\MethodCall|Expr\NullsafeMethodCall|Expr\NullsafePropertyFetch|Expr\PropertyFetch $expression, bool $method, int $depth): string
    {
        if (!$expression->name instanceof Syntax\Identifier) {
            return '';
        }
        $types = [];
        foreach (TypeConstraint::of($this->of($expression->var, $depth + 1))->names() as $owner) {
            $documented = $this->docs->member($owner, $expression->name->toString(), $method, $this->hierarchy);
            if ($documented !== null) {
                $types[] = $documented->objects();

                continue;
            }
            $member = $this->hierarchy->member($owner, $expression->name->toString(), $method ? NodeKind::Method : NodeKind::Property);
            $type = $method ? $member?->declaration()?->signature?->returnType : $member?->declaration()?->type;
            if ($type !== null) {
                $types[] = in_array($type, ['self', 'static'], true) ? $owner : $type;
            }
        }

        return implode('|', array_unique($types));
    }
}
