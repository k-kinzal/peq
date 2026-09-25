<?php

declare(strict_types=1);

namespace Tests\Diff;

use App\Analyzer\Declaration\PhpDoc\DocParser;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Dependency updates must not silently extend the grammar beyond the tested matrix.
 *
 * @internal
 */
#[CoversNothing]
#[Small]
final class PhpDocCoverageTest extends TestCase
{
    /**
     * Every upstream annotation must occur in a positive or explicit negative graph case.
     */
    public function testEveryUpstreamTagHasAnExecutableGraphCase(): void
    {
        $vendor = dirname(__DIR__, 2).'/vendor';
        $upstream = [];
        foreach ([$vendor.'/phpstan/phpdoc-parser/src/Parser/PhpDocParser.php', 'phar://'.$vendor.'/phpstan/phpstan/phpstan.phar/src/PhpDoc/PhpDocNodeResolver.php'] as $file) {
            $source = file_get_contents($file);
            self::assertNotFalse($source);
            $nodes = (new ParserFactory())->createForNewestSupportedVersion()->parse($source);
            foreach ((new NodeFinder())->findInstanceOf($nodes ?? [], String_::class) as $literal) {
                if (preg_match('/^@[a-z]+(?:-[a-z]+)*$/D', $literal->value) === 1) {
                    $upstream[] = $literal->value;
                }
            }
        }
        $covered = [];
        foreach (PhpDocDifferenceTest::providerSources() as [$source]) {
            preg_match_all('/@[a-z]+(?:-[a-z]+)*/', $source, $matches);
            array_push($covered, ...$matches[0]);
        }
        $missing = array_values(array_unique(array_diff($upstream, $covered)));
        sort($missing);

        self::assertNotEmpty($upstream);
        self::assertSame([], $missing, 'Add a graph fixture for every new upstream tag, including metadata that must not create dependencies.');
    }

    /**
     * Checks compound types as well as the tags that contain them.
     */
    public function testEveryParsedTypeShapeIsExercised(): void
    {
        $seen = [];
        $parser = new DocParser();
        foreach (PhpDocDifferenceTest::providerSources() as [$source]) {
            preg_match_all('~/\*\*.*?\*/~s', $source, $comments);
            foreach ($comments[0] as $comment) {
                $pending = [$parser->parse($comment)];
                while ($pending !== []) {
                    $node = array_pop($pending);
                    $seen[$node::class] = true;
                    foreach (get_object_vars($node) as $child) {
                        foreach (is_array($child) ? $child : [$child] as $value) {
                            if ($value instanceof \PHPStan\PhpDocParser\Ast\Node) {
                                $pending[] = $value;
                            }
                        }
                    }
                }
            }
        }
        $missing = [];
        $files = glob(dirname(__DIR__, 2).'/vendor/phpstan/phpdoc-parser/src/Ast/Type/*Node.php');
        foreach ($files === false ? [] : $files as $file) {
            $class = 'PHPStan\PhpDocParser\Ast\Type\\'.basename($file, '.php');
            if (!interface_exists($class) && !isset($seen[$class])) {
                $missing[] = basename($file);
            }
        }

        self::assertSame([], $missing, 'Add a parsed fixture when the upstream type grammar grows.');
    }
}
