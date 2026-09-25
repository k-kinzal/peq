<?php

declare(strict_types=1);

namespace App\Analyzer\Declaration\PhpDoc;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstFetchNode;
use PHPStan\PhpDocParser\Ast\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\Doctrine\DoctrineTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\InvalidTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\MethodTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ObjectShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ThisTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;

/**
 * Extracts class references from types, never from descriptions or shape keys.
 */
final class DocNames
{
    /**
     * PHPStan's pseudo-types and generic type operators do not name classes.
     */
    private const array BUILTINS = [
        'int', 'integer', 'positive-int', 'negative-int', 'non-positive-int', 'non-negative-int', 'non-zero-int',
        'string', 'decimal-int-string', 'non-decimal-int-string', 'lowercase-string', 'uppercase-string', 'literal-string',
        'class-string', 'interface-string', 'trait-string', 'enum-string', 'callable-string', 'array-key',
        'scalar', 'empty-scalar', 'non-empty-scalar', 'number', 'numeric', 'numeric-string', 'non-empty-string',
        'non-empty-lowercase-string', 'non-empty-uppercase-string', 'truthy-string', 'non-falsy-string', 'non-empty-literal-string',
        'bool', 'boolean', 'true', 'false', 'null', 'float', 'double', 'array', 'associative-array', 'non-empty-array',
        'iterable', 'callable', 'pure-callable', 'resource', 'open-resource', 'closed-resource', 'mixed', 'non-empty-mixed',
        'void', 'object', 'callable-object', 'callable-array', 'never', 'noreturn', 'never-return', 'never-returns', 'no-return',
        'list', 'non-empty-list', 'empty', 'key-of', 'value-of', 'int-mask', 'int-mask-of', '*',
        '__stringandstringable', '__stringnotstringable', '__benevolent', 'new', 'template-type',
    ];

    /**
     * @param list<string> $expanding Prevents recursive aliases and bounds from looping
     *
     * @return list<string>
     */
    public static function of(Node $node, DocScope $scope, array $expanding = []): array
    {
        if ($node instanceof InvalidTagValueNode || $node instanceof DoctrineTagValueNode) {
            return [];
        }
        if ($node instanceof CallableTypeNode || $node instanceof MethodTagValueNode) {
            $scope = $scope->withTypes(new PhpDocNode(array_map(static fn (TemplateTagValueNode $template): PhpDocTagNode => new PhpDocTagNode('@template', $template), $node->templateTypes)));
        }
        if ($node instanceof GenericTypeNode && strtolower($node->type->name) === 'int') {
            $bounds = array_filter($node->genericTypes, static fn (TypeNode $type): bool => !$type instanceof IdentifierTypeNode || !in_array($type->name, ['min', 'max'], true));

            return array_values(array_unique(array_merge([], ...array_map(static fn (TypeNode $type): array => self::of($type, $scope, $expanding), $bounds))));
        }
        if ($node instanceof IdentifierTypeNode) {
            return self::identifier($node->name, $scope, $expanding);
        }
        if ($node instanceof ThisTypeNode) {
            return $scope->class === null ? [] : [$scope->class];
        }
        if ($node instanceof ConstFetchNode) {
            return $node->className === '' ? [] : self::identifier($node->className, $scope, $expanding);
        }
        if ($node instanceof ArrayShapeItemNode && $node->keyName instanceof ConstFetchNode) {
            return array_values(array_unique([...self::of($node->keyName, $scope, $expanding), ...self::of($node->valueType, $scope, $expanding)]));
        }
        if ($node instanceof ArrayShapeItemNode || $node instanceof ObjectShapeItemNode) {
            return self::of($node->valueType, $scope, $expanding);
        }
        $names = [];
        foreach (get_object_vars($node) as $child) {
            foreach (is_array($child) ? $child : [$child] as $value) {
                if ($value instanceof Node) {
                    array_push($names, ...self::of($value, $scope, $expanding));
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param list<string> $expanding
     *
     * @return list<string>
     */
    public static function identifier(string $name, DocScope $scope, array $expanding): array
    {
        if (array_key_exists($name, $scope->localTypes)) {
            $type = $scope->localTypes[$name];

            return $type === null || in_array($name, $expanding, true) ? [] : self::of($type, $scope, [...$expanding, $name]);
        }
        if (in_array(strtolower($name), self::BUILTINS, true)) {
            return [];
        }
        if (in_array(strtolower($name), ['pure-closure', 'static-closure', 'static-pure-closure'], true)) {
            return ['Closure'];
        }
        if (str_contains($name, '-') && !str_starts_with($name, 'OCI-')) {
            return [];
        }
        $resolved = $scope->resolve($name);

        return $resolved === null ? [] : [$resolved];
    }
}
