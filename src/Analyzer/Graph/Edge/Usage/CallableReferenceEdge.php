<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Edge\Usage;

use App\Analyzer\Graph\Call\CallOccurrence;
use App\Analyzer\Graph\EdgeKind;
use Override;

/**
 * A first-class callable reference that creates a value without executing its target.
 */
final readonly class CallableReferenceEdge extends CallOccurrence
{
    /**
     * Separates callable creation from the call relations selected by inspection.
     */
    #[Override]
    public function kind(): EdgeKind
    {
        return EdgeKind::CallableReference;
    }
}
