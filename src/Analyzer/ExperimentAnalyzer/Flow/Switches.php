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
        [$entries, $exits] = $this->select($node, $state, $subject);
        $outcome = new Exits(null);
        $fallthrough = null;
        foreach (array_values($node->cases) as $index => $case) {
            if (!isset($entries[$index]) && $fallthrough === null) {
                continue;
            }
            $entry = isset($entries[$index]) ? clone $entries[$index] : $fallthrough;
            assert($entry !== null);
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

    /**
     * Evaluates case tests only along unmatched paths, before executing fallthrough.
     *
     * @return array{array<int, State>, list<State>}
     */
    public function select(Stmt\Switch_ $node, State $state, string $subject): array
    {
        $remaining = clone $state;
        $entries = [];
        $default = null;
        foreach (array_values($node->cases) as $index => $case) {
            if ($case->cond === null) {
                $default = $index;

                continue;
            }
            if (!$remaining->reachable) {
                continue;
            }
            $values = $this->statements->expressions->values($case->cond, $remaining);
            if ($values === []) {
                continue;
            }
            $test = $this->statements->expressions->recording->value($case->cond, 'condition', [$subject, ...$values], $remaining);
            $entries[$index] = clone $remaining;
            $entries[$index]->controls[$test] = 'match';
            $remaining->controls[$test] = 'no-match';
        }
        $exits = $default === null && $remaining->reachable ? [$remaining] : [];
        if ($default !== null && $remaining->reachable) {
            $entries[$default] = $remaining;
        }

        return [$entries, $exits];
    }
}
