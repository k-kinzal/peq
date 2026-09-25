<?php

declare(strict_types=1);

namespace App\Analyzer\Declaration\PhpDoc;

use PHPStan\PhpDocParser\Ast\Type;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;

/**
 * Projects a documented value to its receivers or elements, never to every mentioned class.
 */
final readonly class DocExpression
{
    /**
     * @param list<string> $expanding
     * @param list<self>   $alternatives
     */
    public function __construct(public TypeNode $type, public DocScope $scope, private DocIndex $index, private array $expanding = [], private array $alternatives = []) {}

    /**
     * Combines values without losing the namespace each was declared in.
     *
     * @param list<self> $values
     */
    public static function union(array $values): ?self
    {
        $first = $values[0] ?? null;

        return $first === null ? null : (count($values) === 1 ? $first : new self(new Type\UnionTypeNode([]), $first->scope, $first->index, alternatives: $values));
    }

    /**
     * Keeps lexical imports when descending into a compound type.
     */
    public function nested(TypeNode $type): self
    {
        return new self($type, $this->scope, $this->index, $this->expanding);
    }

    /**
     * Reads object alternatives and intersection constraints at the value itself.
     */
    public function objects(): string
    {
        if ($this->alternatives !== []) {
            return implode('|', array_unique(array_map(static fn (self $value): string => $value->objects(), $this->alternatives)));
        }
        $type = $this->type;
        if ($type instanceof Type\IdentifierTypeNode && array_key_exists($type->name, $this->scope->localTypes)) {
            return $this->index->alias($type->name, $this->scope, $this->expanding)?->objects() ?? '';
        }

        return match (true) {
            $type instanceof Type\IdentifierTypeNode, $type instanceof Type\ThisTypeNode => implode('|', DocNames::of($type, $this->scope)),
            $type instanceof Type\NullableTypeNode => $this->nested($type->type)->objects(),
            $type instanceof Type\UnionTypeNode => implode('|', array_filter(array_map(fn (TypeNode $t): string => $this->nested($t)->objects(), $type->types), static fn (string $name): bool => $name !== '')),
            $type instanceof Type\IntersectionTypeNode => $this->intersection($type),
            $type instanceof Type\GenericTypeNode => $this->nested($type->type)->objects(),
            $type instanceof Type\ConditionalTypeNode, $type instanceof Type\ConditionalTypeForParameterNode => $this->nested($type->if)->objects().'|'.$this->nested($type->else)->objects(),
            $type instanceof Type\CallableTypeNode => $this->nested($type->identifier)->objects(),
            $type instanceof Type\OffsetAccessTypeNode => $this->nested($type->type)->element(trim((string) $type->offset, "'\""))?->objects() ?? '',
            default => '',
        };
    }

    /**
     * Reads an iterable value, optionally selecting one known shape key.
     */
    public function element(?string $key = null): ?self
    {
        if ($this->alternatives !== []) {
            return self::union(array_values(array_filter(array_map(static fn (self $value): ?self => $value->element($key), $this->alternatives), static fn (?self $value): bool => $value !== null)));
        }
        $type = $this->type;
        if ($type instanceof Type\UnionTypeNode) {
            return self::union(array_values(array_filter(array_map(fn (TypeNode $part): ?self => $this->nested($part)->element($key), $type->types), static fn (?self $value): bool => $value !== null)));
        }
        if ($type instanceof Type\IdentifierTypeNode && array_key_exists($type->name, $this->scope->localTypes)) {
            return $this->index->alias($type->name, $this->scope, $this->expanding)?->element($key);
        }
        if ($type instanceof Type\NullableTypeNode) {
            return $this->nested($type->type)->element($key);
        }
        if ($type instanceof Type\ArrayTypeNode) {
            return $this->nested($type->type);
        }
        if ($type instanceof Type\GenericTypeNode && in_array(strtolower($type->type->name), ['array', 'non-empty-array', 'list', 'non-empty-list', 'iterable'], true)) {
            $element = $type->genericTypes[array_key_last($type->genericTypes)] ?? null;

            return $element === null ? null : $this->nested($element);
        }
        if ($type instanceof Type\ArrayShapeNode) {
            return $this->shapeElement($type, $key);
        }

        return null;
    }

    /**
     * Selects shape values, including the remaining entries of an unsealed shape.
     */
    public function shapeElement(Type\ArrayShapeNode $type, ?string $key): ?self
    {
        $elements = [];
        foreach ($type->items as $position => $item) {
            $itemKey = $item->keyName === null ? (string) $position : trim((string) $item->keyName, "'\"");
            if ($key === null || $itemKey === $key) {
                $elements[] = $item->valueType;
            }
        }
        if ($type->unsealedType !== null && ($key === null || $elements === [])) {
            $elements[] = $type->unsealedType->valueType;
        }

        return $elements === [] ? null : $this->nested(new Type\UnionTypeNode($elements));
    }

    /**
     * Distributes union alternatives inside intersections for receiver constraints.
     */
    public function intersection(Type\IntersectionTypeNode $type): string
    {
        $alternatives = [''];
        foreach ($type->types as $member) {
            $names = $this->nested($member)->objects();
            if ($names === '') {
                continue;
            }
            $next = [];
            foreach ($alternatives as $prefix) {
                foreach (explode('|', $names) as $name) {
                    $next[] = $prefix === '' ? $name : $prefix.'&'.$name;
                }
            }
            $alternatives = $next;
        }

        return implode('|', $alternatives);
    }
}
