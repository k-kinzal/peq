<?php

declare(strict_types=1);

namespace App\Analyzer\Declaration\PhpDoc;

use App\Analyzer\Graph\NodeKind;
use App\Analyzer\Graph\Resolution\ClassHierarchy;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypeAliasImportTagValueNode;
use WeakMap;

/**
 * Resolves documented declarations within the analysed source set, without autoloading it.
 */
final class DocIndex
{
    /**
     * @var WeakMap<DocBlock, array<string, array<string, DocExpression>>>
     */
    private WeakMap $selected;

    /**
     * @var array<string, DocBlock>
     */
    public array $blocks = [];

    /**
     * Retains selected types for each parsed comment across receiver lookups.
     */
    public function __construct()
    {
        $this->selected = new WeakMap();
    }

    /**
     * @param list<Node> $nodes
     */
    public function read(array $nodes, NodeVisitor ...$visitors): void
    {
        $names = new NameResolver();
        (new NodeTraverser($names, new DocContext($this, $names), ...$visitors))->traverse($nodes);
    }

    /**
     * Property names are case-sensitive; class and method names are not.
     */
    public static function key(string $name): string
    {
        $property = strpos($name, '::$');

        return $property === false ? strtolower($name) : strtolower(substr($name, 0, $property)).substr($name, $property);
    }

    /**
     * Reads the documentation attached during indexing.
     */
    public static function block(Node $node): ?DocBlock
    {
        $block = $node->getAttribute('peqDocBlock');

        return $block instanceof DocBlock ? $block : null;
    }

    /**
     * @return array<string, DocExpression>
     */
    public function types(?DocBlock $block, string $family): array
    {
        if ($block === null) {
            return [];
        }
        $selected = $this->selected[$block] ?? [];
        if (isset($selected[$family])) {
            return $selected[$family];
        }
        $types = [];
        foreach ($block->types($family) as $name => $type) {
            $types[$name] = new DocExpression($type, $block->scopeFor($family, $name), $this);
        }
        $selected[$family] = $types;
        $this->selected[$block] = $selected;

        return $types;
    }

    /**
     * Finds the documented return value of a named function.
     */
    public function returned(string $function): ?DocExpression
    {
        return $this->types($this->blocks[self::key($function)] ?? null, 'return')[''] ?? null;
    }

    /**
     * Finds written and magic member types through the declared ancestry.
     */
    public function member(string $owner, string $name, bool $method, ClassHierarchy $hierarchy): ?DocExpression
    {
        $member = $hierarchy->member($owner, $name, $method ? NodeKind::Method : NodeKind::Property);
        $declaredOwner = $member === null ? null : ClassHierarchy::owner($member);
        if ($declaredOwner !== null) {
            $key = $method ? $member->id()->toString() : $declaredOwner.'::$'.$name;
            $types = $this->types($this->blocks[self::key($key)] ?? null, $method ? 'return' : 'var');
            if (isset($types[$name]) || isset($types[''])) {
                return $types[$name] ?? $types[''];
            }
        }
        foreach ($hierarchy->ancestors($owner) as $ancestor) {
            if ($method) {
                $inherited = $this->returned($ancestor.'::'.$name);
                if ($inherited !== null) {
                    return $inherited;
                }
            } else {
                $promoted = $this->promoted($ancestor, $name, $hierarchy);
                if ($promoted !== null) {
                    return $promoted;
                }
            }
            $types = $this->types($this->blocks[self::key($ancestor)] ?? null, $method ? 'method' : 'property');
            if (isset($types[$method ? strtolower($name) : $name])) {
                return $types[$method ? strtolower($name) : $name];
            }
        }

        return null;
    }

    /**
     * A promoted property's documented type can come from its constructor parameter.
     */
    public function promoted(string $owner, string $name, ClassHierarchy $hierarchy): ?DocExpression
    {
        foreach ($hierarchy->method($owner, '__construct')?->declaration()?->signature->parameters ?? [] as $parameter) {
            if ($parameter->promoted && $parameter->name === $name) {
                return $this->types($this->blocks[self::key($owner.'::__construct')] ?? null, 'param')[$name] ?? null;
            }
        }

        return null;
    }

    /**
     * Imported aliases retain the declaring file's imports and template scope.
     *
     * @param list<string> $expanding
     */
    public function alias(string $name, DocScope $scope, array $expanding): ?DocExpression
    {
        $key = ($scope->class ?? '').':'.$name;
        if (in_array($key, $expanding, true)) {
            return null;
        }
        $expanding[] = $key;
        $class = $this->blocks[self::key($scope->typeClass ?? $scope->class ?? '')] ?? null;
        foreach ($class?->doc->getTags() ?? [] as $tag) {
            $value = $tag->value;
            if ($value instanceof TypeAliasImportTagValueNode && ($value->importedAs ?? $value->importedAlias) === $name) {
                $from = $scope->resolve($value->importedFrom->name);
                $origin = $this->blocks[self::key($from ?? '')] ?? null;

                return $origin === null ? null : $this->alias($value->importedAlias, $origin->scope, $expanding);
            }
        }
        $type = $scope->localTypes[$name] ?? null;

        return $type === null ? null : new DocExpression($type, $scope, $this, $expanding);
    }
}
