<?php

declare(strict_types=1);

namespace App\Analyzer\DebugAnalyzer\Generator;

use Closure;
use Faker\Generator;

/**
 * Generates the names a PHP codebase is made of.
 *
 * Every name a generated graph carries starts here: namespaces, class-like names,
 * member names and file paths, each following the casing PHP code conventionally
 * uses for it. The underlying random source is held as a collaborator rather than
 * inherited from, so each name-producing operation is an ordinary method with a
 * declared return type instead of a call resolved at runtime through a provider
 * registry.
 *
 * @visibility parent
 */
final class NameGenerator
{
    /**
     * @param Generator $faker The random source the names are drawn from
     */
    public function __construct(
        private readonly Generator $faker,
    ) {}

    /**
     * Generates a PascalCase identifier.
     *
     * @return string Two or three words joined in PascalCase, such as "ExampleUserRecord"
     */
    public function pascalCase(): string
    {
        return implode('', $this->words(static fn (string $word): string => ucfirst($word), 2, 3));
    }

    /**
     * Generates a camelCase identifier.
     *
     * @return string Two or three words joined in camelCase, such as "exampleUserRecord"
     */
    public function camelCase(): string
    {
        return lcfirst($this->pascalCase());
    }

    /**
     * Generates an UPPER_SNAKE_CASE identifier.
     *
     * @return string Two or three words joined in UPPER_SNAKE_CASE, such as "EXAMPLE_USER_RECORD"
     */
    public function upperSnakeCase(): string
    {
        return implode('_', $this->words(static fn (string $word): string => strtoupper($word), 2, 3));
    }

    /**
     * Generates a PHP namespace.
     *
     * @return string One to three PascalCase segments joined by backslashes, such as "App\Billing\Invoice"
     */
    public function namespace(): string
    {
        $segments = [];
        $count = $this->faker->numberBetween(1, 3);
        for ($i = 0; $i < $count; ++$i) {
            $segments[] = $this->pascalCase();
        }

        return implode('\\', $segments);
    }

    /**
     * Generates a PHP class name.
     *
     * @return string A PascalCase name ending in "Class"
     */
    public function className(): string
    {
        return $this->pascalCase().'Class';
    }

    /**
     * Generates a PHP interface name.
     *
     * @return string A PascalCase name ending in "Interface"
     */
    public function interfaceName(): string
    {
        return $this->pascalCase().'Interface';
    }

    /**
     * Generates a PHP trait name.
     *
     * @return string A PascalCase name ending in "Trait"
     */
    public function traitName(): string
    {
        return $this->pascalCase().'Trait';
    }

    /**
     * Generates a PHP enum name.
     *
     * @return string A PascalCase name ending in "Enum"
     */
    public function enumName(): string
    {
        return $this->pascalCase().'Enum';
    }

    /**
     * Generates a PHP method name.
     *
     * @return string A camelCase name ending in "Method"
     */
    public function methodName(): string
    {
        return $this->camelCase().'Method';
    }

    /**
     * Generates a PHP property name.
     *
     * @return string A camelCase name ending in "Property"
     */
    public function propertyName(): string
    {
        return $this->camelCase().'Property';
    }

    /**
     * Generates a PHP constant name.
     *
     * @return string An UPPER_SNAKE_CASE name ending in "_CONST"
     */
    public function constantName(): string
    {
        return $this->upperSnakeCase().'_CONST';
    }

    /**
     * Generates a PHP enum case name.
     *
     * @return string An UPPER_SNAKE_CASE name ending in "_CASE"
     */
    public function enumCaseName(): string
    {
        return $this->upperSnakeCase().'_CASE';
    }

    /**
     * Generates a PHP function name.
     *
     * @return string A camelCase name ending in "Function"
     */
    public function functionName(): string
    {
        return $this->camelCase().'Function';
    }

    /**
     * Generates a path to a PHP file.
     *
     * @return string An absolute path whose file name is PascalCase, such as "/billing/invoice/UserRecord.php"
     */
    public function phpFilePath(): string
    {
        $directories = $this->words(static fn (string $word): string => $word, 1, 4);

        return '/'.implode('/', $directories).'/'.$this->pascalCase().'.php';
    }

    /**
     * Draws a number of words and shapes each one.
     *
     * @param Closure(string): string $shape How each drawn word is written
     * @param int                     $min   Fewest words to draw
     * @param int                     $max   Most words to draw
     *
     * @return list<string> The shaped words
     */
    public function words(Closure $shape, int $min, int $max): array
    {
        $words = [];
        $count = $this->faker->numberBetween($min, $max);
        for ($i = 0; $i < $count; ++$i) {
            $words[] = $shape($this->faker->word());
        }

        return $words;
    }
}
