<?php

declare(strict_types=1);

namespace App\Analyzer\Declaration\PhpDoc;

use PhpParser\NameContext;
use PhpParser\Node\Name;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypeAliasImportTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypeAliasTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;

/**
 * Lexical imports and local type names visible to one documentation comment.
 */
final readonly class DocScope
{
    /**
     * @param array<string, null|TypeNode> $localTypes
     * @param array<string, true>          $classes    Declared class names, lower-cased
     */
    public function __construct(
        public NameContext $names,
        public ?string $class = null,
        public ?string $parent = null,
        public array $localTypes = [],
        public array $classes = [],
        public ?string $typeClass = null,
    ) {}

    /**
     * A template or alias is a local type, never a class of the same spelling.
     */
    public function withTypes(PhpDocNode $doc): self
    {
        $types = $this->localTypes;
        foreach ($doc->getTags() as $tag) {
            $value = $tag->value;
            if ($value instanceof TemplateTagValueNode) {
                $types[$value->name] = $value->bound;
            } elseif ($value instanceof TypeAliasTagValueNode) {
                $types[$value->alias] = $value->type;
            } elseif ($value instanceof TypeAliasImportTagValueNode) {
                $types[$value->importedAs ?? $value->importedAlias] = $value->importedFrom;
            }
        }

        return new self($this->names, $this->class, $this->parent, $types, $this->classes, $this->typeClass);
    }

    /**
     * Trait methods take self from their consumer but keep their original type aliases.
     */
    public function inClass(string $class): self
    {
        return new self($this->names, $class, $this->parent, $this->localTypes, $this->classes, $this->typeClass ?? $this->class);
    }

    /**
     * Resolves class names with the same namespace rules as PHP source names.
     */
    public function resolve(string $name): ?string
    {
        if (in_array(strtolower($name), ['self', 'static', '$this'], true)) {
            return $this->class;
        }
        if (strtolower($name) === 'parent') {
            return $this->parent;
        }
        $node = str_starts_with($name, '\\') ? new Name\FullyQualified(substr($name, 1)) : new Name($name);

        $resolved = $this->names->getResolvedClassName($node)->toString();
        if (!isset($this->classes[strtolower($resolved)]) && (defined($resolved) || defined(ltrim($name, '\\')))) {
            return null;
        }

        return $resolved;
    }
}
