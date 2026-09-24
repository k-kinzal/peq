<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer;

use App\Analyzer\Graph\NodeKind;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;

/**
 * One class, interface, trait or enum an analysed file declares.
 *
 * Analysis has to answer questions about a declaration from somewhere other than
 * where it is written — what a class extends, which methods it writes itself, where
 * the trait it uses lives — and answering them by reflection would mean loading the
 * analysed code into the process that analyses it. This is what stands in for that:
 * everything the walk needs to know about a declaration, read out of the syntax.
 *
 * @visibility namespace
 */
final class ClassLikeDeclaration
{
    /**
     * @param string       $name   The fully qualified name of the declaration
     * @param NodeKind     $kind   Which of the four kinds of declaration it is
     * @param ParsedSource $source The file it is written in
     * @param ClassLike    $node   The declaration itself
     */
    public function __construct(
        public readonly string $name,
        public readonly NodeKind $kind,
        public readonly ParsedSource $source,
        public readonly ClassLike $node,
    ) {}

    /**
     * Reads the kind of a class-like out of its syntax.
     *
     * @param ClassLike $node The declaration met while walking a file
     *
     * @return null|NodeKind The kind it declares, or null when it declares no named symbol
     */
    public static function kindOf(ClassLike $node): ?NodeKind
    {
        return match (true) {
            $node instanceof Interface_ => NodeKind::Interface,
            $node instanceof Trait_ => NodeKind::Trait,
            $node instanceof Enum_ => NodeKind::Enum,
            $node instanceof Class_ => NodeKind::Klass,
            default => null,
        };
    }
}
