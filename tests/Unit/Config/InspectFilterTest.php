<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\InspectFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(InspectFilter::class)]
#[Small]
final class InspectFilterTest extends TestCase
{
    public function testForKindFollowsTheQuestionSuggestedByTheTarget(): void
    {
        self::assertSame(InspectFilter::Calls, InspectFilter::forKind(\App\Analyzer\Graph\NodeKind::Method));
        self::assertSame(InspectFilter::Calls, InspectFilter::forKind(\App\Analyzer\Graph\NodeKind::Function));
        self::assertSame(InspectFilter::Depend, InspectFilter::forKind(\App\Analyzer\Graph\NodeKind::Klass));
        self::assertSame(InspectFilter::Depend, InspectFilter::forKind(\App\Analyzer\Graph\NodeKind::Interface));
        self::assertSame(InspectFilter::All, InspectFilter::forKind(\App\Analyzer\Graph\NodeKind::Property));
    }
}
