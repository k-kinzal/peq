<?php

declare(strict_types=1);

namespace App\Analyzer\PhpStanAnalyzer;

use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\Node\ClassNode;
use App\Analyzer\Graph\Node\FunctionNode;
use App\Analyzer\Graph\Node\MethodNode;
use App\Analyzer\Graph\Node\UnknownNode;
use App\Analyzer\Graph\NodeId\ClassNodeId;
use App\Analyzer\Graph\NodeId\FunctionNodeId;
use App\Analyzer\Graph\NodeId\MethodNodeId;
use App\Analyzer\Graph\NodeId\UnknownNodeId;
use PHPStan\Analyser\Scope;

final class SourceResolver
{
    public static function resolve(Scope $scope): Node
    {
        if ($scope->isInClass()) {
            $classReflection = $scope->getClassReflection();
            $className = $classReflection->getName();
            $functionReflection = $scope->getFunction();

            if ($functionReflection !== null) {
                // Method
                $methodName = $functionReflection->getName();

                return new MethodNode(
                    new MethodNodeId(self::getNamespace($className), self::getShortName($className), $methodName),
                    true, // We assume it exists if we are in it
                    null
                );
            }

            // Inside class but not in method? (e.g. property default value, const value)
            // So we return ClassNode, but the edges won't be created.
            return new ClassNode(
                new ClassNodeId(self::getNamespace($className), self::getShortName($className)),
                true,
                null
            );
        }
        if ($scope->getFunction() !== null) {
            // Global function
            $functionName = $scope->getFunction()->getName();

            return new FunctionNode(
                new FunctionNodeId(self::getNamespace($functionName), self::getShortName($functionName)),
                true,
                null
            );
        }

        // Global scope
        return new UnknownNode(new UnknownNodeId($scope->getFile()));
    }

    public static function isBuiltin(string $name): bool
    {
        return in_array(strtolower($name), ['self', 'static', 'parent', 'int', 'string', 'float', 'bool', 'array', 'iterable', 'callable', 'void', 'object', 'mixed', 'null', 'false', 'true', 'never'], true);
    }

    public static function getNamespace(string $name): string
    {
        $lastSlash = strrpos($name, '\\');
        if ($lastSlash === false) {
            return '';
        }

        return substr($name, 0, $lastSlash);
    }

    public static function getShortName(string $name): string
    {
        $lastSlash = strrpos($name, '\\');
        if ($lastSlash === false) {
            return $name;
        }

        return substr($name, $lastSlash + 1);
    }
}
