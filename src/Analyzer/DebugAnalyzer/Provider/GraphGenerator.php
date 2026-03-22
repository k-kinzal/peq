<?php

declare(strict_types=1);

namespace App\Analyzer\DebugAnalyzer\Provider;

use Closure;
use Faker\Generator;

/**
 * Custom Faker generator that aggregates all graph-related providers.
 *
 * This class extends the base Faker Generator and provides type hints for all
 * custom provider methods through mixin annotations. It serves as a central
 * point for accessing all fake data generation capabilities needed for creating
 * dependency graphs.
 *
 * @mixin PrimitivesProvider Methods for generating primitive PHP naming conventions
 * @mixin AtomicProvider Methods for generating atomic graph elements (NodeIds, EdgeKinds, FileMeta)
 * @mixin ComponentProvider Methods for generating complete graph components (Nodes, Edges)
 * @mixin GraphProvider Methods for generating complete dependency graphs
 *
 * @method self optional(float $weight = 0.5, mixed $default = null)
 * @method self unique(bool $reset = false, int $maxRetries = 10000)
 * @method self valid(Closure|null $validator = null, int $maxRetries = 10000)
 */
class GraphGenerator extends Generator {}
