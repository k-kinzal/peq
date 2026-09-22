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
        $metadata = (new IssueAction())->metadata(['schemaVersion' => 2, 'target' => 'f', 'file' => '/f.php', 'direction' => 'uses', 'roots' => ['call'], 'provenance' => ['engine' => 'ExperimentAnalyzer'], 'analysis' => ['status' => 'partial', 'complete' => false, 'frontier' => [], 'nodes' => ['call' => 'unknown'], 'issues' => [['code' => 'OPAQUE_CALL', 'nodeId' => 'call', 'rule' => 'checked-rules/v1', 'reason' => 'Unknown effects', 'affects' => ['effects'], 'source' => ['line' => 4, 'column' => 2, 'endLine' => 6, 'text' => 'secret']]]], 'nodes' => ['secret'], 'structure' => ['secret'], 'diagnostics' => ['secret']]);
        self::assertSame(['schemaVersion' => 2, 'target' => 'f', 'file' => '/f.php', 'direction' => 'uses', 'roots' => ['call'], 'provenance' => ['engine' => 'ExperimentAnalyzer'], 'analysis' => ['status' => 'partial', 'complete' => false, 'frontier' => []], 'issues' => [['code' => 'OPAQUE_CALL', 'nodeId' => 'call', 'rule' => 'checked-rules/v1', 'reason' => 'Unknown effects', 'affects' => ['effects'], 'location' => ['line' => 4, 'column' => 2, 'endLine' => 6]]]], $metadata);
    }

    /**
     * @throws JsonException
     */
    public function testPrepareLabelsTheExactMetadataOnlyPreview(): void
    {
        vfsStream::setup('report', null, ['result.json' => '{"experimental":true,"schemaVersion":2,"target":"f","analysis":{"status":"input","complete":true}}']);
        $draft = (new IssueAction())->prepare('vfs://report/result.json', description: 'Incorrect origin.');
        self::assertSame("Experimental analysis report\n\nIncorrect origin.\n\nSource included: no\n\n```json\n{\n    \"schemaVersion\": 2,\n    \"target\": \"f\",\n    \"analysis\": {\n        \"status\": \"input\",\n        \"complete\": true\n    },\n    \"issues\": []\n}\n```\n", $draft->body);
    }

    /**
     * @throws JsonException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidArtifacts')]
    public function testPrepareRejectsUnrelatedOrIncompleteJson(string $text): void
    {
        vfsStream::setup('report', null, ['result.json' => $text]);
        $this->expectException(InspectionRejected::class);
        $this->expectExceptionMessage('Expected an experimental inspect schemaVersion 2 result.');
        (new IssueAction())->prepare('vfs://report/result.json');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerInvalidArtifacts(): iterable
    {
        yield 'production result' => ['{"experimental":false,"schemaVersion":2,"target":"f","analysis":{}}'];

        yield 'old schema' => ['{"experimental":true,"schemaVersion":1,"target":"f","analysis":{}}'];

        yield 'missing target' => ['{"experimental":true,"schemaVersion":2,"analysis":{}}'];

        yield 'missing analysis' => ['{"experimental":true,"schemaVersion":2,"target":"f"}'];

        yield 'scalar json' => ['true'];
    }

    /**
     * @throws JsonException
     */
    public function testPrepareRejectsOversizedSourceWithoutSendingOrTruncating(): void
    {
        vfsStream::setup('report', null, ['result.json' => json_encode(['experimental' => true, 'schemaVersion' => 2, 'target' => 'f', 'analysis' => [], 'nodes' => [str_repeat('x', 60000)]], JSON_THROW_ON_ERROR)]);
        $sender = $this->createMock(IssueSender::class);
        $sender->expects(self::never())->method('send');
        $this->expectException(InspectionRejected::class);
        $this->expectExceptionMessage('The issue exceeds 60 KB. Select a smaller result or omit --include-source; nothing was sent.');
        (new IssueAction($sender))->prepare('vfs://report/result.json', true);
    }

    /**
     * @throws JsonException
     */
    public function testPrepareRejectsOversizedArtifactsBeforeDecoding(): void
    {
        vfsStream::setup('report', null, ['result.json' => str_repeat('x', 2000001)]);
        $this->expectException(InspectionRejected::class);
        $this->expectExceptionMessage('Provide a readable experimental JSON result smaller than 2 MB.');
        (new IssueAction())->prepare('vfs://report/result.json');
    }

    public function testSendPublishesOnlyThePreparedDraft(): void
    {
        $draft = new IssueDraft('Title', 'Reviewed body');
        $sender = $this->createMock(IssueSender::class);
        $sender->expects(self::once())->method('send')->with(self::identicalTo($draft))->willReturn('https://example.test/issue');
        self::assertSame('https://example.test/issue', (new IssueAction($sender))->send($draft));
    }
}
