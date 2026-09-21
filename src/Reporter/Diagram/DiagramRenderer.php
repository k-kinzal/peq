<?php

declare(strict_types=1);

namespace App\Reporter\Diagram;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * A way of writing a drawing down.
 *
 * What a drawing holds — the symbols and the relations between them — is decided
 * before it is written, once, so that two ways of writing it cannot disagree about
 * what is in it. One draws it in the terminal it was asked for in; another writes it
 * as Mermaid, for a page that renders it.
 *
 * @visibility App\Reporter
 */
interface DiagramRenderer
{
    /**
     * Writes a drawing, or nothing when it holds nothing.
     *
     * @param Diagram         $diagram The symbols and relations to write
     * @param OutputInterface $output  Where they are written
     */
    public function render(Diagram $diagram, OutputInterface $output): void;
}
