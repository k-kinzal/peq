<?php

declare(strict_types=1);

namespace App\Analyzer;

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
    private readonly ?string $owner;

    /**
     * Seeds receiver types from the callable signature and lexical owner.
     */
    public function __construct(private readonly ClassHierarchy $hierarchy, Node $source)
    {
        $this->owner = ClassHierarchy::owner($source);
        foreach ($source->declaration()?->signature->parameters ?? [] as $parameter) {
            $this->variables[$parameter->name] = $parameter->type ?? '';
        }
    }

    /**
     * Finds the declared object type of an expression, or an empty string when unknown.
     */
    public function of(Expr $expression, int $depth = 0): string
    {
        if ($depth > 16) {
            return '';
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
        $known = $this->variables[$name] ?? '';
        $type = $this->of($assignment->expr);
        $this->variables[$name] = implode('|', array_unique(array_filter([$known, $type], static fn (string $part): bool => $part !== '')));
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
            $member = $this->hierarchy->member($owner, $expression->name->toString(), $method ? NodeKind::Method : NodeKind::Property);
            $type = $method ? $member?->declaration()?->signature?->returnType : $member?->declaration()?->type;
            if ($type !== null) {
                $types[] = in_array($type, ['self', 'static'], true) ? $owner : $type;
            }
        }

        return implode('|', array_unique($types));
    }
}
