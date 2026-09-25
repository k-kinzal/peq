<?php

declare(strict_types=1);

namespace App\Analyzer\Declaration\PhpDoc;

use App\Analyzer\Graph\Graph;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;

/**
 * Adds written PHPDoc dependencies to either engine's completed declarations.
 */
final class DocDependencies
{
    /**
     * @param list<string> $files
     */
    public static function enrich(Graph $graph, array $files, Parser $parser): Graph
    {
        $docs = new DocParser();
        $classes = DocVisitor::classes($graph);
        foreach ($files as $file) {
            $text = is_readable($file) ? file_get_contents($file) : false;
            if ($text === false || !str_contains($text, '/**')) {
                continue;
            }
            $errors = new Collecting();
            $nodes = $parser->parse($text, $errors);
            if ($nodes === null || $errors->hasErrors()) {
                continue;
            }
            $names = new NameResolver($errors);
            $visitor = new DocVisitor($graph, $file, $docs, $names, $classes);
            (new NodeTraverser($names, $visitor))->traverse($nodes);
        }

        return $graph;
    }
}
