<?php

declare(strict_types=1);

namespace App\Action\Experimental;

/**
 * The sole external side effect of experimental issue reporting.
 */
interface IssueSender
{
    /**
     * Sends an already reviewed draft and returns its published URL.
     *
     * @throws InspectionRejected If publishing fails
     */
    public function send(IssueDraft $draft): string;
}
