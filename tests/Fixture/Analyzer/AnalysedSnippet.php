<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer;

use App\Analyzer\AnalysisFailedException;
use App\Analyzer\Graph\Graph;
use App\Analyzer\PhpStanAnalyzer\PhpStanAnalyzer;
use RuntimeException;

/**
 * A snippet of PHP, analysed as though it were a source tree.
 *
 * A contract about what peq reads out of a construct is clearest when the construct
 * is written out in the check itself. peq analyses directories, so the snippet is
 * written to one and taken away again, and the shapes a construct is usually written
 * in — inside a condition, a loop, a closure — are supplied here so that a check
 * states the construct and not the scaffolding around it.
 */
final class AnalysedSnippet
{
    /**
     * Analyses one snippet of PHP.
     *
     * @param string $phpCode The source to analyse, starting with its open tag
     *
     * @return Graph The graph peq reads out of it
     *
     * @throws AnalysisFailedException If the analysis cannot run at all
     * @throws RuntimeException        If the snippet cannot be written to disk
     */
    public static function graph(string $phpCode): Graph
    {
        $directory = sys_get_temp_dir().'/peq-snippet-'.uniqid();
        if (!mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create the temporary directory "%s"', $directory));
        }

        $file = $directory.'/Snippet.php';
        if (file_put_contents($file, $phpCode) === false) {
            throw new RuntimeException(sprintf('Unable to write the snippet "%s"', $file));
        }

        try {
            return (new PhpStanAnalyzer())->analyze($directory);
        } finally {
            unlink($file);
            rmdir($directory);
        }
    }

    /**
     * Writes a statement inside a method of a class that has something to reach for.
     *
     * @param string $body The statements to place in the method body
     *
     * @return string The source of a file holding them
     */
    public static function inMethodBody(string $body): string
    {
        return <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Fixture\\Snippet;

            class Dep extends \\Exception {
                public const SOME_CONST = 1;
                public static int \$staticProp = 1;
                public static function staticMethod(): void {}
            }

            class Subject {
                public function testMethod(mixed \$y = null): mixed {
                    {$body}
                    return null;
                }
            }
            PHP;
    }

    /**
     * Writes a statement inside a method of a file that declares a global function.
     *
     * @param string $body The statements to place in the method body
     *
     * @return string The source of a file holding them
     */
    public static function inFunctionCallContext(string $body): string
    {
        return <<<PHP
            <?php
            declare(strict_types=1);

            function dep_func(): mixed { return null; }

            class Subject {
                public function testMethod(mixed \$y = null): mixed {
                    {$body}
                    return null;
                }
            }
            PHP;
    }

    /**
     * Writes a statement inside a class that also declares a method to call.
     *
     * @param string $body The statements to place in the method body
     *
     * @return string The source of a file holding them
     */
    public static function inMethodCallContext(string $body): string
    {
        return <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Fixture\\Snippet;

            class Subject {
                public function helperMethod(): int { return 1; }
                public function testMethod(mixed \$y = null): mixed {
                    {$body}
                    return null;
                }
            }
            PHP;
    }

    /**
     * Writes a statement inside a class that also declares a property to reach.
     *
     * @param string $body The statements to place in the method body
     *
     * @return string The source of a file holding them
     */
    public static function inPropertyAccessContext(string $body): string
    {
        return <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Fixture\\Snippet;

            class Subject {
                public int \$targetProp = 0;
                public function testMethod(mixed \$y = null): mixed {
                    {$body}
                    return null;
                }
            }
            PHP;
    }

    /**
     * Writes a type in a parameter position, with a class the type can name.
     *
     * @param string $typeHint The type as it would be written
     *
     * @return string The source of a file declaring it
     */
    public static function asParameterType(string $typeHint): string
    {
        return self::withDependency(sprintf('public function testMethod(%s $x): void {}', $typeHint));
    }

    /**
     * Writes a type in a return position, with a class the type can name.
     *
     * @param string $typeHint The type as it would be written
     *
     * @return string The source of a file declaring it
     */
    public static function asReturnType(string $typeHint): string
    {
        return self::withDependency(sprintf('public function testMethod(): %s { return new Dep(); }', $typeHint));
    }

    /**
     * Writes a type in a property position, with a class the type can name.
     *
     * @param string $typeHint The type as it would be written
     *
     * @return string The source of a file declaring it
     */
    public static function asPropertyType(string $typeHint): string
    {
        return self::withDependency(sprintf('public %s $prop;', $typeHint));
    }

    /**
     * Writes a class member beside a dependency its declaration can name.
     *
     * @param string $member The member declaration to place in the class body
     *
     * @return string The source of a file holding it
     */
    public static function withDependency(string $member): string
    {
        return <<<PHP
            <?php
            declare(strict_types=1);
            namespace Tests\\Fixture\\Snippet;

            class Dep implements \\Stringable {
                public function __toString(): string { return ''; }
            }

            class Subject {
                {$member}
            }
            PHP;
    }
}
