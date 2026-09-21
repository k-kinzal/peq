<?php

declare(strict_types=1);

namespace App\Action\Experimental;

use RuntimeException;

/**
 * The requested experimental inspection has no reliable answer.
 */
final class InspectionRejected extends RuntimeException {}
