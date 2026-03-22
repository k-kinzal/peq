<?php

declare(strict_types=1);

namespace App\Analyzer\Graph;

/**
 * Provides validation methods for PHP identifiers and namespaces.
 *
 * This trait offers reusable validation logic for complex identifier checks.
 * For simple validations (e.g., empty string checks), use assertions directly
 * in the consuming class rather than this trait.
 */
trait IdentifierAssert
{
    /**
     * Asserts that a value is a valid PHP identifier.
     *
     * Validates identifiers used for class names, method names, property names,
     * function names, etc. according to PHP naming rules:
     * - First character: a-z, A-Z, _, or 0x80-0xff (multibyte)
     * - Subsequent characters: above characters plus 0-9
     * - Must not be an empty string
     *
     * @param string                 $value       The value to validate as a PHP identifier
     * @param null|string|\Throwable $description Optional description for assertion failure messages
     */
    protected static function assertIdentifier(string $value, string|\Throwable|null $description = null): void
    {
        assert(
            $value !== '' && preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $value) === 1,
            $description
        );
    }

    /**
     * Asserts that a value is a valid PHP namespace.
     *
     * Validates fully qualified namespaces according to PHP naming rules:
     * - Backslash-separated segments
     * - Each segment must be a valid PHP identifier
     * - Must not be an empty string
     *
     * @param string                 $value       The value to validate as a PHP namespace
     * @param null|string|\Throwable $description Optional description for assertion failure messages
     */
    protected static function assertNamespace(string $value, string|\Throwable|null $description = null): void
    {
        assert(
            $value !== '' && preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\\\\\x80-\xff]*$/', $value) === 1,
            $description
        );
    }
}
