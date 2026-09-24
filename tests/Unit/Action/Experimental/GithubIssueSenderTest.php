<?php

declare(strict_types=1);

namespace Tests\Unit\Action\Experimental;

use App\Action\Experimental\GithubIssueSender;
use App\Action\Experimental\InspectionRejected;
use App\Action\Experimental\IssueDraft;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Action\Experimental')]
final class GithubIssueSenderTest extends TestCase
{
    public function testSendPassesTheReviewedBodyThroughStdinWithoutAShell(): void
    {
        $sender = new GithubIssueSender([PHP_BINARY, '-r', 'echo hash("sha256", stream_get_contents(STDIN));', '--']);
        self::assertSame('ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad', $sender->send(new IssueDraft('$(this must not execute)', 'abc')));
    }

    public function testSendReportsFailureWithoutClaimingPublication(): void
    {
        $sender = new GithubIssueSender([PHP_BINARY, '-r', 'fwrite(STDERR, "offline failure"); exit(1);', '--']);
        $this->expectException(InspectionRejected::class);
        $this->expectExceptionMessage('offline failure');
        $sender->send(new IssueDraft('Title', 'Body'));
    }
}
