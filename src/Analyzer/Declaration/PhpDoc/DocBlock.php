<?php

declare(strict_types=1);

namespace App\Analyzer\Declaration\PhpDoc;

use PHPStan\PhpDocParser\Ast\PhpDoc\MethodTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PropertyTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;

/**
 * A parsed comment and the namespace and template scope it was written in.
 */
final readonly class DocBlock
{
    /**
     * Retains the parsed tags and their lexical scope.
     */
    public function __construct(public PhpDocNode $doc, public DocScope $scope) {}

    /**
     * PHPStan-specific annotations override Psalm, Phan and ordinary annotations,
     * independently of their order in the comment. Dependencies still retain all tags.
     *
     * @return array<string, TypeNode>
     */
    public function types(string $family): array
    {
        $types = [];
        $tags = match ($family) {
            'return' => ['@return', '@phan-return', '@phan-real-return', '@psalm-return', '@phpstan-return'],
            'property' => ['@property', '@phpstan-property', '@property-read', '@phpstan-property-read'],
            default => ['@'.$family, '@phan-'.$family, '@psalm-'.$family, '@phpstan-'.$family],
        };
        foreach ($tags as $name) {
            foreach ($this->doc->getTagsByName($name) as $tag) {
                $value = $tag->value;
                $key = match (true) {
                    $value instanceof ParamTagValueNode => ltrim($value->parameterName, '$'),
                    $value instanceof VarTagValueNode => ltrim($value->variableName, '$'),
                    $value instanceof PropertyTagValueNode => ltrim($value->propertyName, '$'),
                    $value instanceof MethodTagValueNode => strtolower($value->methodName),
                    default => '',
                };
                $type = match (true) {
                    $value instanceof ParamTagValueNode => $value->isVariadic ? new \PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode($value->type) : $value->type,
                    $value instanceof VarTagValueNode, $value instanceof PropertyTagValueNode, $value instanceof ReturnTagValueNode => $value->type,
                    $value instanceof MethodTagValueNode => $value->returnType,
                    default => null,
                };
                if ($type !== null) {
                    $types[$key] = $type;
                }
            }
        }

        return $types;
    }
}
