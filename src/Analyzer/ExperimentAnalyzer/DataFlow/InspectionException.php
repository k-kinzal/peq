<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\DataFlow;

use RuntimeException;

/**
 * A target or construct for which the experiment cannot give an honest answer.
 */
final class InspectionException extends RuntimeException {}
