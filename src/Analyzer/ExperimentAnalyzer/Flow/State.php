<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Flow;

/**
 * Reaching definitions and predicates on the paths that still continue.
 */
final class State
{
    /**
     * @param array<string, array<string, true>> $definitions
     * @param array<string, string>              $controls
     */
    public function __construct(
        public array $definitions = [],
        public array $controls = [],
        public bool $reachable = true,
    ) {}

    /**
     * @param non-empty-list<self> $states
     */
    public static function join(array $states): self
    {
        $joined = clone $states[0];
        foreach ($states as $state) {
            foreach ($state->definitions as $name => $definitions) {
                $joined->definitions[$name] = ($joined->definitions[$name] ?? []) + $definitions;
            }
            $joined->controls = array_intersect_assoc($joined->controls, $state->controls);
        }

        return $joined;
    }

    /**
     * Replaces an expression's environment with the paths that actually finish it.
     *
     * @param list<self> $states
     */
    public function continueWith(array $states): void
    {
        $live = array_values(array_filter($states, static fn (self $state): bool => $state->reachable));
        $this->reachable = $live !== [];
        if ($live !== []) {
            $joined = self::join($live);
            $this->definitions = $joined->definitions;
            $this->controls = $joined->controls;
        }
    }

    /**
     * Compares definition sets independently of insertion order.
     */
    public function sameDefinitions(self $other): bool
    {
        $left = $this->definitions;
        $right = $other->definitions;
        ksort($left);
        ksort($right);
        foreach ($left as &$definitions) {
            ksort($definitions);
        }
        unset($definitions);
        foreach ($right as &$definitions) {
            ksort($definitions);
        }

        return $left === $right;
    }
}
