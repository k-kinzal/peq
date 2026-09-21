<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Flow;

use PhpParser\Node\Stmt;

/**
 * Case entry and fallthrough are distinct paths; break leaves only the switch.
 */
final readonly class Switches
{
    /**
     * Creates this value with its explicit analysis inputs.
     */
    public function __construct(private Statements $statements) {}

    /**
     * Reads dependencies and updates the environments of continuing paths.
     */
    public function read(Stmt\Switch_ $node, State $state): Exits
    {
        $subject = $this->statements->expressions->read($node->cond, $state);
        $remaining = clone $state;
        $entries = [];
        $default = null;
        foreach ($node->cases as $index => $case) {
            if ($case->cond === null) {
                $default = $index;

                continue;
            }
            $value = $this->statements->expressions->read($case->cond, $remaining);
            $test = $this->statements->expressions->recording->value($case->cond, 'condition', [$subject, $value], $remaining);
            $entries[$index] = clone $remaining;
            $entries[$index]->controls[$test] = 'match';
            $remaining->controls[$test] = 'no-match';
        }
        $exits = $default === null ? [$remaining] : [];
        if ($default !== null) {
            $entries[$default] = $remaining;
        }
        $outcome = new Exits(null);
        $fallthrough = null;
        foreach ($node->cases as $index => $case) {
            $entry = clone $entries[$index];
            $entry->controls[$subject] = 'case-entry';
            $result = $this->statements->read($case->stmts, $fallthrough === null ? $entry : State::join([$entry, $fallthrough]));
            array_push($exits, ...($result->breaks[1] ?? []), ...($result->continues[1] ?? []));
            $outcome->absorb($result, true);
            $fallthrough = $result->normal;
            if ($fallthrough !== null) {
                $fallthrough->controls[$subject] = 'case-entry';
            }
        }
        if ($fallthrough !== null) {
            $exits[] = $fallthrough;
        }
        $outcome->normal = $exits === [] ? null : State::join($exits);

        return $outcome;
    }
}
