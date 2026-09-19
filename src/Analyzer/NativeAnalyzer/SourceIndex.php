<?php

declare(strict_types=1);

namespace App\Analyzer\NativeAnalyzer;

use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Everything the analysed files declare, read before any of them is walked.
 *
 * A walk cannot be a single pass over the sources, because what one file means
 * depends on what another declares: a class takes its methods on from a trait
 * declared elsewhere, and an unqualified call means the function of the current
 * namespace when there is one and the global function when there is not. Both
 * questions are about the whole analysed set, so the whole set is read first.
 *
 * Reading it first is also what keeps the analyzer honest about its own scope. The
 * reference engine follows a trait only into a file it was asked to analyse, and
 * resolves a function name only against functions it can see; an index built from
 * exactly the analysed files answers both questions the same way, without loading a
 * line of the analysed code into the process doing the analysis.
 *
 * @visibility namespace
 */
final class SourceIndex
{
    /**
     * @param string                              $workingDirectory The directory the analysis runs in
     * @param AutoloadIndex                       $autoloaded       The classes the project can reach beyond the analysed files
     * @param array<string, ParsedSource>         $sourcesByPath    The analysed files, keyed by path, in the order they are analysed
     * @param array<string, ClassLikeDeclaration> $classLikes       The declarations they hold, keyed by lower-cased name
     * @param array<string, true>                 $functions        The functions they declare, keyed by lower-cased name
     */
    public function __construct(
        private readonly string $workingDirectory,
        private readonly AutoloadIndex $autoloaded,
        private readonly array $sourcesByPath,
        private readonly array $classLikes,
        private readonly array $functions,
    ) {}

    /**
     * Reads every analysed file and indexes what it declares.
     *
     * A file that cannot be read, or that PHP itself would refuse, declares nothing
     * as far as analysis is concerned and is left out. That is what the reference
     * engine does with it: a file it cannot parse is reported as a diagnostic about
     * the analysed code, not as a failure of the analysis, and the rest of the
     * codebase is still read.
     *
     * @param list<string> $files            Absolute paths of the files to analyse
     * @param string       $workingDirectory The directory the analysis runs in, which anonymous class names are relative to
     *
     * @return self The index of those files
     */
    public static function of(array $files, string $workingDirectory): self
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $sources = [];
        $classLikes = [];
        $functions = [];

        foreach ($files as $file) {
            $statements = self::parse($parser, $file);
            if ($statements === null) {
                continue;
            }
            $source = new ParsedSource($file, $statements, AnonymousClassNaming::of($statements));
            $sources[$file] = $source;

            foreach ((new NodeFinder())->find($statements, static fn (object $node): bool => $node instanceof ClassLike || $node instanceof Function_) as $declaration) {
                if ($declaration instanceof Function_ && $declaration->namespacedName !== null) {
                    $functions[strtolower($declaration->namespacedName->toString())] = true;

                    continue;
                }
                if (!$declaration instanceof ClassLike || $declaration->namespacedName === null) {
                    continue;
                }
                $kind = ClassLikeDeclaration::kindOf($declaration);
                $name = $declaration->namespacedName->toString();
                if ($kind !== null && !isset($classLikes[strtolower($name)])) {
                    $classLikes[strtolower($name)] = new ClassLikeDeclaration($name, $kind, $source, $declaration);
                }
            }
        }

        return new self($workingDirectory, AutoloadIndex::at($workingDirectory), $sources, $classLikes, $functions);
    }

    /**
     * Parses one file with every written name resolved to its full form.
     *
     * @param Parser $parser The parser to read the file with
     * @param string $file   Absolute path of the file
     *
     * @return null|list<Stmt> The statements of that file, or null when PHP would refuse it
     */
    public static function parse(Parser $parser, string $file): ?array
    {
        $contents = is_readable($file) ? file_get_contents($file) : false;
        if ($contents === false) {
            return null;
        }

        $errors = new Collecting();
        $parsed = $parser->parse($contents, $errors);

        if ($parsed === null || $errors->hasErrors()) {
            return null;
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        $resolved = [];
        foreach ($traverser->traverse($parsed) as $statement) {
            if ($statement instanceof Stmt) {
                $resolved[] = $statement;
            }
        }

        return $resolved;
    }

    /**
     * Writes a file path the way anonymous class names are keyed by it.
     *
     * @param string $file             Absolute path of the file
     * @param string $workingDirectory The directory the analysis runs in
     *
     * @return string The path relative to that directory, or the path itself when it lies outside
     */
    public static function relativePath(string $file, string $workingDirectory): string
    {
        if ($workingDirectory !== '' && str_starts_with($file, $workingDirectory)) {
            return str_replace('\\', '/', substr($file, strlen($workingDirectory) + 1));
        }

        return str_replace('\\', '/', $file);
    }

    /**
     * Writes the path of an analysed file the way anonymous class names are keyed by it.
     *
     * @param string $file Absolute path of the file
     *
     * @return string The path relative to the directory the analysis runs in
     */
    public function relativePathOf(string $file): string
    {
        return self::relativePath($file, $this->workingDirectory);
    }

    /**
     * Returns the analysed files, in the order they are analysed.
     *
     * @return list<ParsedSource> The parsed files
     */
    public function sources(): array
    {
        return array_values($this->sourcesByPath);
    }

    /**
     * Returns one analysed file by its path.
     *
     * @param string $file Absolute path of the file
     *
     * @return null|ParsedSource The parsed file, or null when it is not one of the analysed files
     */
    public function sourceOf(string $file): ?ParsedSource
    {
        return $this->sourcesByPath[$file] ?? null;
    }

    /**
     * Returns the declaration of a class-like, when the analysed files hold one.
     *
     * @param string $name The fully qualified name, in any casing
     *
     * @return null|ClassLikeDeclaration The declaration, or null when no analysed file writes it
     */
    public function classLike(string $name): ?ClassLikeDeclaration
    {
        return $this->classLikes[strtolower($name)] ?? null;
    }

    /**
     * Reports whether the analysed files declare a function of that name.
     *
     * @param string $name The fully qualified function name, in any casing
     *
     * @return bool True when one of the analysed files declares it
     */
    public function declaresFunction(string $name): bool
    {
        return isset($this->functions[strtolower($name)]);
    }

    /**
     * Reports whether the name of a class is one analysis can find a declaration for.
     *
     * A name the reference engine cannot reflect is one it will not resolve `parent`
     * to, so the two engines have to agree on what is findable. Both find what the
     * analysed files declare and what PHP itself declares, and neither loads the
     * analysed code to find anything else.
     *
     * @param string $name The fully qualified class name
     *
     * @return bool True when the declaration of that class can be found
     */
    public function knowsClass(string $name): bool
    {
        return $this->classLike($name) !== null || $this->autoloaded->knows($name);
    }
}
