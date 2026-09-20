<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer;

use App\Analyzer\SourceParser;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;

/**
 * The syntax tree of a file as it is written, read again from disk.
 *
 * PHPStan hands a collector a cleaned tree: its CleaningVisitor strips expressions
 * out of method and closure bodies, and it can do so partially, leaving a body that
 * looks present but has lost the calls and property accesses peq is there to record.
 * Reading the file again is therefore not an optimisation to be removed — it is the
 * only way to see the whole body.
 *
 * A file is parsed once per instance and kept, because a file holds many methods and
 * every one of them would otherwise pay for the same parse. The lifetime of that
 * memory is the lifetime of this object, which is one analysis run.
 *
 * The file is read for the same PHP version the analysis is run for. A file the
 * analysis could read and this could not would lose every relation written inside
 * its method bodies without losing the declarations around them, which is the one
 * way a dependency graph can be wrong while looking complete.
 *
 * @visibility namespace
 */
final class ReparsedSource
{
    /**
     * @var array<string, null|list<Stmt>> Statements per file, with null recording a file that could not be read
     */
    private array $parsedFiles = [];

    /**
     * The parser, built on first use because building it is not free.
     */
    private ?Parser $parser = null;

    /**
     * The traverser that resolves names, reused across files.
     */
    private ?NodeTraverser $traverser = null;

    /**
     * The finder used to locate declarations inside a parsed file.
     */
    private ?NodeFinder $finder = null;

    /**
     * @param null|int $phpVersion The PHP version the sources are read as, in PHP_VERSION_ID
     *                             form, or null to read them as the version peq runs on
     */
    public function __construct(
        private readonly ?int $phpVersion = null,
    ) {}

    /**
     * The parser that reads a file as the configured PHP version, built once.
     *
     * Building it is not free, and a run reads many files with it.
     *
     * @return Parser The parser for the configured version, or for the version peq
     *                runs on when no version was configured
     */
    public function parser(): Parser
    {
        return $this->parser ??= SourceParser::forVersion($this->phpVersion);
    }

    /**
     * Returns the statements of a file, with every name resolved to its full form.
     *
     * A file that cannot be read or cannot be parsed yields null, and the failure is
     * remembered so the same file is not retried for each of its methods.
     *
     * @param string $file Absolute path of the file
     *
     * @return null|list<Stmt> The parsed statements, or null when the file is unusable
     */
    public function statements(string $file): ?array
    {
        if (array_key_exists($file, $this->parsedFiles)) {
            return $this->parsedFiles[$file];
        }

        $contents = is_readable($file) ? file_get_contents($file) : false;
        if ($contents === false) {
            return $this->parsedFiles[$file] = null;
        }

        $syntaxErrors = new Collecting();
        $parsed = $this->parser()->parse($contents, $syntaxErrors);

        if ($parsed === null || $syntaxErrors->hasErrors()) {
            return $this->parsedFiles[$file] = null;
        }

        if ($this->traverser === null) {
            $this->traverser = new NodeTraverser();
            $this->traverser->addVisitor(new NameResolver());
        }

        $resolved = [];
        foreach ($this->traverser->traverse($parsed) as $statement) {
            if ($statement instanceof Stmt) {
                $resolved[] = $statement;
            }
        }

        return $this->parsedFiles[$file] = $resolved;
    }

    /**
     * Returns the body of one method as it is written in the file.
     *
     * @param string $file       Absolute path of the file the method is declared in
     * @param string $className  Fully qualified name of the declaring class-like
     * @param string $methodName Name of the method
     *
     * @return null|list<Stmt> The method body, or null when it has none or cannot be located
     */
    public function methodBody(string $file, string $className, string $methodName): ?array
    {
        $statements = $this->statements($file);
        if ($statements === null) {
            return null;
        }

        $finder = $this->finder ??= new NodeFinder();

        $declaration = $finder->findFirst($statements, static function (PhpParserNode $node) use ($className): bool {
            return ($node instanceof Class_ || $node instanceof Interface_ || $node instanceof Trait_ || $node instanceof Enum_)
                && $node->namespacedName !== null
                && $node->namespacedName->toString() === $className;
        });

        if (!$declaration instanceof Class_ && !$declaration instanceof Trait_ && !$declaration instanceof Enum_) {
            return null;
        }

        $method = $finder->findFirst($declaration->stmts, static function (PhpParserNode $node) use ($methodName): bool {
            return $node instanceof ClassMethod && $node->name->toString() === $methodName;
        });

        return $method instanceof ClassMethod && $method->stmts !== null ? array_values($method->stmts) : null;
    }
}
