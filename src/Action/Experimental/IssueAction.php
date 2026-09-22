<?php

declare(strict_types=1);

namespace App\Action\Experimental;

use JsonException;

/**
 * Prepares a portable report from saved results, with source inclusion opt-in.
 */
final readonly class IssueAction
{
    /**
     * Creates the reporter with an injectable external transport.
     */
    public function __construct(private IssueSender $sender = new GithubIssueSender()) {}

    /**
     * Builds the complete preview locally, including reports of incorrect resolved results.
     *
     * @throws InspectionRejected If the artifact is invalid or too large
     * @throws JsonException      If the report cannot be encoded
     */
    public function prepare(string $path, bool $includeSource = false, string $description = ''): IssueDraft
    {
        $text = is_file($path) && is_readable($path) ? file_get_contents($path, length: 2000001) : false;
        if ($text === false || strlen($text) > 2000000) {
            throw new InspectionRejected('Provide a readable experimental JSON result smaller than 2 MB.');
        }

        try {
            $result = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InspectionRejected('Invalid experimental JSON: '.$error->getMessage(), previous: $error);
        }
        if (!is_array($result) || ($result['experimental'] ?? null) !== true || ($result['schemaVersion'] ?? null) !== 2 || !is_string($result['target'] ?? null) || !is_array($result['analysis'] ?? null)) {
            throw new InspectionRejected('Expected an experimental inspect schemaVersion 2 result.');
        }
        $payload = $includeSource ? $result : $this->metadata($result);
        $body = "Experimental analysis report\n\n".$description."\n\nSource included: ".($includeSource ? 'yes' : 'no')."\n\n```json\n".json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n```\n";
        if (strlen($body) > 60000) {
            throw new InspectionRejected('The issue exceeds 60 KB. Select a smaller result or omit --include-source; nothing was sent.');
        }

        return new IssueDraft('Experimental analysis: '.substr($result['target'], 0, 150), $body);
    }

    /**
     * Selects metadata without recursively retaining source text or graph snippets.
     *
     * @param array<array-key, mixed> $result
     *
     * @return array<string, mixed>
     */
    public function metadata(array $result): array
    {
        $analysis = is_array($result['analysis'] ?? null) ? $result['analysis'] : [];
        $issues = [];
        foreach (is_array($analysis['issues'] ?? null) ? $analysis['issues'] : [] as $issue) {
            if (is_array($issue)) {
                $location = is_array($issue['source'] ?? null) ? $issue['source'] : [];
                $issues[] = array_intersect_key($issue, array_flip(['code', 'nodeId', 'rule', 'reason', 'affects'])) + ['location' => array_intersect_key($location, array_flip(['line', 'column', 'endLine']))];
            }
        }

        return array_intersect_key($result, array_flip(['schemaVersion', 'target', 'file', 'direction', 'roots', 'provenance'])) + ['analysis' => array_intersect_key($analysis, array_flip(['status', 'complete', 'frontier'])), 'issues' => $issues];
    }

    /**
     * Sends the exact draft confirmed by the command's user.
     *
     * @throws InspectionRejected If publishing fails
     */
    public function send(IssueDraft $draft): string
    {
        return $this->sender->send($draft);
    }
}
