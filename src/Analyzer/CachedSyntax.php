<?php

declare(strict_types=1);

namespace App\Analyzer;

use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;

/**
 * File-local name resolution, reusable even when another file changes its declarations.
 *
 * Object identities, declaration indexes and graph relations are rebuilt from these
 * statements; anonymous class numbering must never persist spl_object_id values.
 */
final readonly class CachedSyntax
{
    /**
     * @param null|list<Stmt> $statements Null also caches a syntactically invalid file
     */
    public function __construct(public ?array $statements) {}

    /**
     * Reads or restores namespaced statements for exactly these file contents.
     */
    public static function read(string $file, Parser $parser, ?int $phpVersion = null, ?PhaseCache $cache = null): self
    {
        $contents = is_readable($file) ? file_get_contents($file) : false;
        if ($contents === false) {
            return new self(null);
        }
        $parse = static fn (): self => self::parse($contents, $parser);

        return $cache === null ? $parse() : $cache->remember(
            'syntax',
            $file.':'.($phpVersion ?? PHP_VERSION_ID),
            hash('sha256', $contents),
            self::class,
            $parse,
        );
    }

    /**
     * Resolves names within one file, recording syntax errors as an absent tree.
     * Receiver call columns stay with the cached syntax after the text is discarded.
     */
    public static function parse(string $contents, Parser $parser): self
    {
        $errors = new Collecting();
        $parsed = $parser->parse($contents, $errors);
        if ($parsed === null || $errors->hasErrors()) {
            return new self(null);
        }
        foreach ((new NodeFinder())->find($parsed, static fn (Node $node): bool => $node instanceof MethodCall || $node instanceof NullsafeMethodCall) as $call) {
            $offset = $call->getStartFilePos();
            $newline = strrpos($contents, "\n", $offset - strlen($contents));
            $call->setAttribute('peqStartColumn', $offset - ($newline === false ? -1 : $newline));
        }
        $resolved = [];
        foreach ((new NodeTraverser(new NameResolver()))->traverse($parsed) as $statement) {
            if ($statement instanceof Stmt) {
                $resolved[] = $statement;
            }
        }

        return new self($resolved);
    }
}
