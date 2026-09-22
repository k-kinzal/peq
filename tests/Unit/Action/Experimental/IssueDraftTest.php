<?php

declare(strict_types=1);

namespace Tests\Unit\Action\Experimental;

use App\Action\Experimental\IssueDraft;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Action\Experimental')]
final class IssueDraftTest extends TestCase
{
    public function testValuePreservesTheExactPreview(): void
    {
        $draft = new IssueDraft('Title', "body\n");
        self::assertSame('Title', $draft->title);
        self::assertSame("body\n", $draft->body);
    }
}
