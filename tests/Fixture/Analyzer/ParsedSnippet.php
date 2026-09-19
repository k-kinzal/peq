<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\NativeAnalyzer\AnalysisScope;
use App\Analyzer\NativeAnalyzer\AnonymousClassNaming;
use App\Analyzer\NativeAnalyzer\GraphRecorder;
use App\Analyzer\NativeAnalyzer\ParsedSource;
use App\Analyzer\NativeAnalyzer\SourceIndex;
use App\Analyzer\NativeAnalyzer\SourceWalker;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Expr;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use RuntimeException;

/**
 * A piece of PHP source, read the way the analyzer reads one.
 *
 * Tests of the parts an analysis is assembled from need a syntax tree to hand them,
 * and building one node by node says nothing about what the analyzer will meet in a
 * real file. Parsing the source instead keeps each test about the source it quotes.
 */
final class ParsedSnippet
{
    /**
     * Parses a snippet, with every written name resolved to its full form.
     *
     * @param string $code The source, starting with its opening tag
     *
     * @return list<Stmt> The statements it holds
     *
     * @throws RuntimeException If the snippet cannot be parsed
     */
    public static function statements(string $code): array
    {
        $parsed = (new ParserFactory())->createForHostVersion()->parse($code);
        if ($parsed === null) {
            throw new RuntimeException('The snippet cannot be parsed.');
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
     * Returns the first class-like a snippet declares.
     *
     * @param string $code The source, starting with its opening tag
     *
     * @return ClassLike The declaration
     *
     * @throws RuntimeException If the snippet declares none
     */
    public static function classLike(string $code): ClassLike
    {
        $declaration = (new NodeFinder())->findFirstInstanceOf(self::statements($code), ClassLike::class);
        if (!$declaration instanceof ClassLike) {
            throw new RuntimeException('The snippet declares no class-like.');
        }

        return $declaration;
    }

    /**
     * Returns the first method a snippet declares.
     *
     * @param string $code The source, starting with its opening tag
     *
     * @return ClassMethod The declaration
     *
     * @throws RuntimeException If the snippet declares none
     */
    public static function method(string $code): ClassMethod
    {
        $declaration = (new NodeFinder())->findFirstInstanceOf(self::statements($code), ClassMethod::class);
        if (!$declaration instanceof ClassMethod) {
            throw new RuntimeException('The snippet declares no method.');
        }

        return $declaration;
    }

    /**
     * Reads a snippet the way an analysis reads one of the files it is given.
     *
     * @param string $path Absolute path the snippet stands for
     * @param string $code The source, starting with its opening tag
     *
     * @return ParsedSource The file, parsed
     *
     * @throws RuntimeException If the snippet cannot be parsed
     */
    public static function source(string $path, string $code): ParsedSource
    {
        $statements = self::statements($code);

        return new ParsedSource($path, $statements, AnonymousClassNaming::of($statements));
    }

    /**
     * Returns the expression a snippet assigns.
     *
     * @param string $written The expression, as it is written
     *
     * @return Expr The parsed expression
     *
     * @throws RuntimeException If the snippet cannot be parsed
     */
    public static function expression(string $written): Expr
    {
        $assignment = (new NodeFinder())->findFirstInstanceOf(
            self::statements(sprintf("<?php\nnamespace App;\n\$written = %s;\n", $written)),
            Expr\Assign::class,
        );
        if (!$assignment instanceof Expr\Assign) {
            throw new RuntimeException('The snippet assigns no expression.');
        }

        return $assignment->expr;
    }

    /**
     * Returns the type a snippet writes on a property.
     *
     * @param string $written The type, as it is written
     *
     * @return null|PhpParserNode The parsed type, or null when none is written
     *
     * @throws RuntimeException If the snippet cannot be parsed
     */
    public static function writtenType(string $written): ?PhpParserNode
    {
        $property = (new NodeFinder())->findFirstInstanceOf(
            self::statements(sprintf("<?php\nclass Written { public %s \$held; }\n", $written)),
            Property::class,
        );
        if (!$property instanceof Property) {
            throw new RuntimeException('The snippet declares no property.');
        }

        return $property->type;
    }

    /**
     * Returns the first `use` statement a snippet writes in a class.
     *
     * @param string $code The source, starting with its opening tag
     *
     * @return TraitUse The parsed statement
     *
     * @throws RuntimeException If the snippet writes none
     */
    public static function traitUse(string $code): TraitUse
    {
        $use = (new NodeFinder())->findFirstInstanceOf(self::statements($code), TraitUse::class);
        if (!$use instanceof TraitUse) {
            throw new RuntimeException('The snippet writes no use statement.');
        }

        return $use;
    }

    /**
     * Returns the first statement of the given kind a snippet writes in a class.
     *
     * @template T of Stmt
     *
     * @param string          $code The source, starting with its opening tag
     * @param class-string<T> $kind The kind of statement to find
     *
     * @return T The parsed statement
     *
     * @throws RuntimeException If the snippet writes none
     */
    public static function memberStatement(string $code, string $kind): Stmt
    {
        $statement = (new NodeFinder())->findFirstInstanceOf(self::statements($code), $kind);
        if (!$statement instanceof $kind) {
            throw new RuntimeException(sprintf('The snippet writes no %s.', $kind));
        }

        return $statement;
    }

    /**
     * Returns the first parameter a snippet writes.
     *
     * @param string $code The source, starting with its opening tag
     *
     * @return Param The parsed parameter
     *
     * @throws RuntimeException If the snippet writes none
     */
    public static function parameter(string $code): Param
    {
        $param = (new NodeFinder())->findFirstInstanceOf(self::statements($code), Param::class);
        if (!$param instanceof Param) {
            throw new RuntimeException('The snippet writes no parameter.');
        }

        return $param;
    }

    /**
     * Returns the declaration an index holds under a name.
     *
     * @param SourceIndex $index What the analysed files declare
     * @param string      $name  The name of the declaration
     *
     * @return ClassLike The declaration
     *
     * @throws RuntimeException If the index holds none
     */
    public static function declarationIn(SourceIndex $index, string $name): ClassLike
    {
        $declaration = $index->classLike($name);
        if ($declaration === null) {
            throw new RuntimeException(sprintf('The analysed files declare no "%s".', $name));
        }

        return $declaration->node;
    }

    /**
     * Returns a scope standing somewhere in a file of one snippet.
     *
     * @param null|string $className  The class to stand in, or null outside one
     * @param null|string $methodName The method to stand in, or null outside one
     *
     * @return AnalysisScope The scope
     *
     * @throws RuntimeException If the snippet cannot be written
     */
    public static function scopeIn(?string $className, ?string $methodName): AnalysisScope
    {
        $index = self::index(['Written.php' => "<?php\nnamespace App;\nclass Invoice {}\n"]);
        $scope = AnalysisScope::inFile($index, $index->sources()[0]->path);
        if ($className !== null) {
            $scope = $scope->enteringClass($className, null);
        }

        return $methodName === null ? $scope : $scope->enteringMethod($methodName);
    }

    /**
     * Returns the symbol a snippet's relations are recorded against.
     *
     * @param string $className  The class the relations are written in
     * @param string $methodName The method they are written in
     *
     * @return MethodNode That method, as the graph names it
     */
    public static function writtenBy(string $className, string $methodName): MethodNode
    {
        return new MethodNode(MethodNodeId::of($className, $methodName), true, null);
    }

    /**
     * Returns somewhere for a snippet's relations to stand.
     *
     * @return FileMeta A place in a file
     */
    public static function writtenAt(): FileMeta
    {
        return new FileMeta('/project/Invoice.php', 3, 1);
    }

    /**
     * Walks one snippet the way an analysis walks a file, and returns its graph.
     *
     * @param string $code The source, starting with its opening tag
     *
     * @return Graph The graph that walking it describes
     *
     * @throws RuntimeException If the snippet cannot be written
     */
    public static function walked(string $code): Graph
    {
        $index = self::index(['Walked.php' => $code]);
        $recorder = new GraphRecorder();
        (new SourceWalker($index, $recorder))->walkFile($index->sources()[0]);

        return $recorder->graph();
    }

    /**
     * Writes snippets out as files and returns the directory holding them.
     *
     * @param array<string, string> $files The source of each file, by file name
     *
     * @return string The directory they were written to
     *
     * @throws RuntimeException If they cannot be written
     */
    public static function writeTo(array $files): string
    {
        $directory = sys_get_temp_dir().'/peq-snippet-'.md5(serialize($files));
        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create the directory "%s"', $directory));
        }

        foreach ($files as $file => $code) {
            if (file_put_contents($directory.'/'.$file, $code) === false) {
                throw new RuntimeException(sprintf('Unable to write the file "%s"', $directory.'/'.$file));
            }
        }

        $resolved = realpath($directory);

        return $resolved === false ? $directory : $resolved;
    }

    /**
     * Indexes snippets the way an analysis indexes the files it is given.
     *
     * @param array<string, string> $files The source of each file, by file name
     *
     * @return SourceIndex The index of those files
     *
     * @throws RuntimeException If they cannot be written
     */
    public static function index(array $files): SourceIndex
    {
        $directory = self::writeTo($files);

        return SourceIndex::of(array_values(array_map(static fn (string $file): string => $directory.'/'.$file, array_combine(array_keys($files), array_keys($files)))), $directory);
    }
}
