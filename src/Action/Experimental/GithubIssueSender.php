<?php

declare(strict_types=1);

namespace App\Action\Experimental;

use Override;

/**
 * Uses the user's authenticated GitHub CLI only after explicit confirmation.
 */
final class GithubIssueSender implements IssueSender
{
    /**
     * @param non-empty-list<string> $command Executable prefix, injectable for offline tests
     */
    public function __construct(private readonly array $command = ['gh']) {}

    /**
     * Publishes exactly the reviewed body; source paths are never read here.
     *
     * @throws InspectionRejected If the GitHub CLI is unavailable or fails
     */
    #[Override]
    public function send(IssueDraft $draft): string
    {
        $process = proc_open([...$this->command, 'issue', 'create', '--repo', 'github.com/k-kinzal/peq', '--title', $draft->title, '--body-file', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new InspectionRejected('Cannot start gh. Install and authenticate the GitHub CLI before sending.');
        }
        fwrite($pipes[0], $draft->body);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0 || $output === false) {
            throw new InspectionRejected('GitHub issue submission failed: '.($error === false ? 'unknown error' : trim($error)));
        }

        return trim($output);
    }
}
