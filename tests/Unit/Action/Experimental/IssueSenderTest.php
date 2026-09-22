<?php

declare(strict_types=1);

namespace Tests\Unit\Action\Experimental;

use App\Action\Experimental\IssueAction;
use App\Action\Experimental\IssueDraft;
use App\Action\Experimental\IssueSender;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Action\Experimental')]
final class IssueSenderTest extends TestCase
{
    public function testSendReceivesExactlyTheConfirmedDraft(): void
    {
        $draft = new IssueDraft('Title', 'Body');
        $sender = $this->createMock(IssueSender::class);
        $sender->expects(self::once())->method('send')->with(self::identicalTo($draft))->willReturn('https://example.test/issue');
        self::assertSame('https://example.test/issue', (new IssueAction($sender))->send($draft));
    }
}
