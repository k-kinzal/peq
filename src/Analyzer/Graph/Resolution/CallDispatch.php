<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Resolution;

use App\Analyzer\Graph\Declaration\Visibility;
use App\Analyzer\Graph\Edge\Usage\MethodCallEdge;
use App\Analyzer\Graph\Edge\Usage\PossibleCallEdge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\NodeKind;

/**
 * Adds possible bodies without replacing the contract recorded at the call site.
 */
final class CallDispatch
{
    /**
     * Records possible method bodies compatible with each call site receiver.
     */
    public static function enrich(Graph $graph): void
    {
        $hierarchy = new ClassHierarchy($graph);
        $types = $hierarchy->concreteTypes();
        $candidates = [];
        foreach ($graph->forwardEdges() as $call) {
            if (!$call instanceof MethodCallEdge || $call->expression !== null) {
                continue;
            }
            $target = $call->to()->toString();
            $separator = strrpos($target, '::');
            if ($separator === false) {
                continue;
            }
            $method = substr($target, $separator + 2);
            $receiver = $call->receiverType ?? substr($target, 0, $separator);
            $constraint = TypeConstraint::of($receiver);
            $declared = $hierarchy->node($target);
            if ($declared?->declaration()?->visibility === Visibility::Private) {
                continue;
            }
            $candidates[$receiver] ??= array_values(array_filter($types, static fn ($type): bool => $constraint->accepts($type->id()->toString(), $hierarchy)));
            foreach ($candidates[$receiver] as $type) {
                $body = $hierarchy->method($type->id()->toString(), $method);
                if ($body !== null && $body->resolved() && $body->id()->toString() !== $target
                    && $hierarchy->node(ClassHierarchy::owner($body) ?? '')?->kind() !== NodeKind::Interface
                    && !($body->declaration()?->modifiers->abstract ?? false)
                    && $body->declaration()?->visibility !== Visibility::Private
                ) {
                    $graph->addEdge(new PossibleCallEdge($call, $body->id(), $receiver, $type->id()->toString()));
                }
            }
        }
    }
}
