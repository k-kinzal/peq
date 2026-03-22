<?php

declare(strict_types=1);

namespace App\Analyzer\DebugAnalyzer\Provider;

use Faker\Provider\Base;

/**
 * Faker provider for generating primitive PHP naming conventions and identifiers.
 *
 * This provider generates various PHP naming patterns used in code, such as
 * class names, method names, property names, etc., following common PHP
 * naming conventions (PascalCase, camelCase, UPPER_SNAKE_CASE).
 *
 * @property GraphGenerator $generator
 */
final class PrimitivesProvider extends Base
{
    /**
     * Generates a PascalCase identifier.
     *
     * @return string Multiple words combined in PascalCase (e.g., "ExampleUserData")
     */
    public function pascalCase(): string
    {
        $words = $this->generator->array(fn () => ucfirst($this->generator->word()), 2, 3);

        return implode('', $words);
    }

    /**
     * Generates a camelCase identifier.
     *
     * @return string Multiple words combined in camelCase (e.g., "exampleUserData")
     */
    public function camelCase(): string
    {
        $words = $this->generator->array(fn () => ucfirst($this->generator->word()), 2, 3);
        $words[0] = lcfirst($words[0]);

        return implode('', $words);
    }

    /**
     * Generates an UPPER_SNAKE_CASE identifier.
     *
     * @return string Multiple words combined in UPPER_SNAKE_CASE (e.g., "EXAMPLE_USER_DATA")
     */
    public function upperSnakeCase(): string
    {
        $words = $this->generator->array(fn () => strtoupper($this->generator->word()), 2, 3);

        return implode('_', $words);
    }

    /**
     * Generates a PHP namespace string.
     *
     * @return string A namespace with 1-3 parts separated by backslashes (e.g., "App\Service\Handler")
     */
    public function namespace(): string
    {
        $parts = $this->generator->array(fn () => $this->pascalCase(), 1, 3);

        return implode('\\', $parts);
    }

    /**
     * Generates a PHP class name.
     *
     * @return string A PascalCase class name (e.g., "UserService")
     */
    public function className(): string
    {
        return $this->pascalCase().'Class';
    }

    /**
     * Generates a PHP interface name.
     *
     * @return string A PascalCase name with "Interface" suffix (e.g., "UserServiceInterface")
     */
    public function interfaceName(): string
    {
        return $this->pascalCase().'Interface';
    }

    /**
     * Generates a PHP trait name.
     *
     * @return string A PascalCase name with "Trait" suffix (e.g., "LoggableTrait")
     */
    public function traitName(): string
    {
        return $this->pascalCase().'Trait';
    }

    /**
     * Generates a PHP enum name.
     *
     * @return string A PascalCase name with "Enum" suffix (e.g., "StatusEnum")
     */
    public function enumName(): string
    {
        return $this->pascalCase().'Enum';
    }

    /**
     * Generates a PHP method name.
     *
     * @return string A camelCase method name (e.g., "getUserData")
     */
    public function methodName(): string
    {
        return $this->camelCase().'Method';
    }

    /**
     * Generates a PHP property name.
     *
     * @return string A camelCase property name (e.g., "userData")
     */
    public function propertyName(): string
    {
        return $this->camelCase().'Property';
    }

    /**
     * Generates a PHP constant name.
     *
     * @return string An UPPER_SNAKE_CASE constant name (e.g., "MAX_SIZE")
     */
    public function constantName(): string
    {
        return $this->upperSnakeCase().'_CONST';
    }

    /**
     * Generates a PHP enum case name.
     *
     * @return string An UPPER_SNAKE_CASE enum case name (e.g., "ACTIVE")
     */
    public function enumCaseName(): string
    {
        return $this->upperSnakeCase().'_CASE';
    }

    /**
     * Generates a PHP function name.
     *
     * @return string A camelCase function name (e.g., "parseData")
     */
    public function functionName(): string
    {
        return $this->camelCase().'Function';
    }

    /**
     * Generates a PHP filename.
     *
     * @return string A PascalCase filename with .php extension (e.g., "UserService.php")
     */
    public function phpFilename(): string
    {
        return $this->pascalCase().'.php';
    }

    /**
     * Generates a PHP file path.
     *
     * @return string A full file path with random directory structure (e.g., "/app/service/UserService.php")
     */
    public function phpFilePath(): string
    {
        $parts = $this->generator->array(fn () => $this->generator->word());
        $dirPath = implode('/', $parts);

        return '/'.$dirPath.'/'.$this->phpFilename();
    }

    /**
     * Generates an array of elements using a callback function.
     *
     * The array will contain between min and max elements, each generated
     * by calling the provided callback function.
     *
     * @template T
     *
     * @param callable(): T $callback Function to generate each array element
     * @param int           $min      Minimum number of elements (default: 5)
     * @param int           $max      Maximum number of elements (default: 20)
     *
     * @return array<T> Array of generated elements
     */
    public function array(callable $callback, int $min = 5, int $max = 20): array
    {
        $count = $this->generator->numberBetween($min, $max);
        $result = [];
        for ($i = 0; $i < $count; ++$i) {
            $result[] = $callback();
        }

        return $result;
    }
}
