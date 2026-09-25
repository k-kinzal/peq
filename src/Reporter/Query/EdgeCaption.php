<?php

declare(strict_types=1);

namespace App\Reporter\Query;

use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\StringDatum;

/**
 * Draws distinct call occurrences with their written arguments and source locations.
 */
final class EdgeCaption
{
    /**
     * Retains ordinary relation labels when a query edge has no call-site facts.
     */
    public static function of(EdgeDatum $edge): string
    {
        $expression = $edge->property('expression');
        if (!$edge->property('callSite') instanceof StringDatum || !$expression instanceof StringDatum) {
            return $edge->label();
        }

        return $edge->label().': '.str_replace(["\r", "\n", "\t"], ['\r', '\n', '\t'], $expression->toText())
            .' @ '.$edge->property('file')->toText().':'.$edge->property('line')->toText().':'.$edge->property('column')->toText();
    }
}
