<?php

declare(strict_types=1);

namespace App\Reporter;

use App\Analyzer\Graph\Call\CallOccurrence;
use App\Analyzer\Graph\Call\CallSite;
use App\Analyzer\Graph\Direction;
use App\Analyzer\Graph\Edge;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\InverseEdge;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\NodeId;

/**
 * Reads the occurrences supporting a reported branch without changing its expansion.
 *
 * @phpstan-type ReportedCall array{id: string, expression: string, arguments: list<string>, argumentNames: list<null|string>, argumentTypes: list<null|string>, file: string, line: int, column: int, offset: null|int, endOffset: int, enclosingSymbol: string, callableReference: bool}
 */
final class CallOccurrences
{
    /**
     * @param null|NodeId<Node> $parent
     * @param NodeId<Node>      $child
     *
     * @return list<CallSite>
     */
    public static function between(Graph $graph, ?NodeId $parent, NodeId $child, Direction $direction): array
    {
        $sites = [];
        foreach ($parent === null ? [] : $graph->edges($parent) as $edge) {
            if ($edge->kind()->direction() !== $direction || $edge->to()->toString() !== $child->toString()) {
                continue;
            }
            $site = self::site($edge);
            if ($site !== null) {
                $sites[$site->id()] = $site;
            }
        }

        return array_values($sites);
    }

    /**
     * Reads the authored site in either traversal direction.
     */
    public static function site(Edge $edge): ?CallSite
    {
        $forward = $edge instanceof InverseEdge ? $edge->invert() : $edge;

        return $forward instanceof CallOccurrence ? $forward->site : null;
    }

    /**
     * Makes multiline source readable on one terminal line without losing its spelling.
     */
    public static function text(CallSite $site): string
    {
        return str_replace(["\r", "\n", "\t"], ['\r', '\n', '\t'], $site->expression).' @ '.$site->meta->path.':'.$site->meta->line.':'.$site->meta->column;
    }

    /**
     * Each occurrence has its own label, including calls with identical arguments.
     */
    public static function label(Edge $edge): string
    {
        $site = self::site($edge);

        return $edge->kind()->value.($site === null ? '' : ': '.self::text($site));
    }

    /**
     * @return ReportedCall
     */
    public static function json(CallSite $site): array
    {
        return [
            'id' => $site->id(),
            'expression' => $site->expression,
            'arguments' => array_map(static fn ($argument): string => $argument->text, $site->arguments),
            'argumentNames' => array_map(static fn ($argument): ?string => $argument->name, $site->arguments),
            'argumentTypes' => array_map(static fn ($argument): ?string => $argument->type, $site->arguments),
            'file' => $site->meta->path,
            'line' => $site->meta->line,
            'column' => $site->meta->column,
            'offset' => $site->meta->offset,
            'endOffset' => $site->endOffset,
            'enclosingSymbol' => $site->owner->id()->toString(),
            'callableReference' => $site->callableReference,
        ];
    }
}
