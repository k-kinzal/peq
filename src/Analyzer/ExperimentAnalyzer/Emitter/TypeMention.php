<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Emitter;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\QualifiedName;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;

/**
 * One class-like named in a written type, and where it is written.
 *
 * A declared type may name several types at once — a union, an intersection, or a
 * union of intersections — and each of them stands at its own position in the source.
 * Pairing the name with its position lets a return type, a parameter type and a
 * property type each build the relation its own declaration calls for, without any of
 * them repeating how a written type is taken apart.
 *
 * @visibility App\Analyzer\ExperimentAnalyzer
 */
final class TypeMention
{
    /**
     * @param ClassNode $node The class-like the type names
     * @param FileMeta  $meta Where in the source the name is written
     */
    public function __construct(
        public readonly ClassNode $node,
        public readonly FileMeta $meta,
    ) {}

    /**
     * Reads the class-likes a written type names, with their positions.
     *
     * Builtin names are left out: they name nothing a codebase declares, so there is
     * no symbol for a relation to point at.
     *
     * @param null|PhpParserNode $type The written type, or null when none is written
     * @param string             $file The file the type is written in
     *
     * @return list<self> One mention per class-like the type names
     */
    public static function of(?PhpParserNode $type, string $file): array
    {
        $mentions = [];
        foreach (self::namesOf($type) as $name) {
            $written = $name->toString();
            if ((new QualifiedName($written))->isBuiltinType()) {
                continue;
            }

            $mentions[] = new self(
                new ClassNode(ClassNodeId::of($written), false, null),
                new FileMeta($file, $name->getStartLine(), 1),
            );
        }

        return $mentions;
    }

    /**
     * Reads every class-like name a written type mentions.
     *
     * @param null|PhpParserNode $type The written type, or null when none is written
     *
     * @return list<Name> The names the type mentions, in the order they are written
     */
    public static function namesOf(?PhpParserNode $type): array
    {
        if ($type instanceof Name) {
            return [$type];
        }
        if ($type instanceof NullableType) {
            return self::namesOf($type->type);
        }
        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            $names = [];
            foreach ($type->types as $inner) {
                array_push($names, ...self::namesOf($inner));
            }

            return $names;
        }

        return [];
    }
}
