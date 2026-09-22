<?php

declare(strict_types=1);

namespace Tests\Unit\Action\Experimental;

use App\Action\Experimental\InspectionRejected;
use App\Action\Experimental\IssueAction;
use App\Action\Experimental\IssueDraft;
use App\Action\Experimental\IssueSender;
use JsonException;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNamespace('App\Action\Experimental')]
final class IssueActionTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testPrepareAcceptsResolvedButIncorrectResultsAndExcludesSourceByDefault(): void
    {
        vfsStream::setup('report', null, ['result.json' => '{"experimental":true,"schemaVersion":2,"target":"f","analysis":{"status":"resolved","complete":true,"issues":[]},"nodes":[{"text":"secret source"}]}']);
        $sender = $this->createMock(IssueSender::class);
        $sender->expects(self::never())->method('send');
        $draft = (new IssueAction($sender))->prepare('vfs://report/result.json', description: 'Expected a dependency; observed none.');
        self::assertSame('Experimental analysis: f', $draft->title);
        self::assertStringContainsString('Expected a dependency; observed none.', $draft->body);
        self::assertStringNotContainsString('secret source', $draft->body);
    }

    /**
     * @throws JsonException
     */
    public function testPrepareIncludesOnlySavedSourceWhenExplicitlyRequested(): void
    {
        vfsStream::setup('report', null, ['result.json' => '{"experimental":true,"schemaVersion":2,"target":"f","analysis":{"status":"partial"},"file":"/must/not/be/read.php","nodes":[{"text":"saved source"}]}']);
        $draft = (new IssueAction())->prepare('vfs://report/result.json', true);
        self::assertStringContainsString('saved source', $draft->body);
        self::assertStringContainsString('Source included: yes', $draft->body);
    }

    /**
     * @throws JsonException
     */
    public function testPrepareRejectsInvalidArtifactsLocally(): void
    {
        vfsStream::setup('report', null, ['result.json' => '{']);
        $this->expectException(InspectionRejected::class);
        $this->expectExceptionMessage('Invalid experimental JSON');
        (new IssueAction())->prepare('vfs://report/result.json');
    }

    public function testMetadataRetainsReasonAndLocationWithoutSnippets(): void
    {
        $metadata = (new IssueAction())->metadata(['target' => 'f', 'analysis' => ['status' => 'partial', 'issues' => [['code' => 'OPAQUE_CALL', 'reason' => 'Unknown effects', 'source' => ['line' => 4, 'text' => 'secret']]]]]);
        self::assertSame(['target' => 'f', 'analysis' => ['status' => 'partial'], 'issues' => [['code' => 'OPAQUE_CALL', 'reason' => 'Unknown effects', 'location' => ['line' => 4]]]], $metadata);
    }

    public function testSendPublishesOnlyThePreparedDraft(): void
    {
        $draft = new IssueDraft('Title', 'Reviewed body');
        $sender = $this->createMock(IssueSender::class);
        $sender->expects(self::once())->method('send')->with(self::identicalTo($draft))->willReturn('https://example.test/issue');
        self::assertSame('https://example.test/issue', (new IssueAction($sender))->send($draft));
    }
}
