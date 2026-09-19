<?php

declare(strict_types=1);

namespace App\Analyzer\Graph;

/**
 * A fully qualified PHP name, split into the parts an identifier is built from.
 *
 * Analysis meets names as single strings — `App\Billing\Invoice`, `int`, `parent` —
 * while a node identifier is built from a namespace and a short name. Splitting one
 * into the other is one rule, so it lives in one type rather than being repeated
 * wherever an identifier happens to be constructed.
 *
 * The same type answers whether a name is a PHP builtin, because that is a question
 * about the name and not about the node it might become: a builtin never has a
 * declaration to point at.
 */
final readonly class QualifiedName
{
    /**
     * The grammar of a single name segment, as the PHP manual writes it.
     */
    private const string IDENTIFIER_PATTERN = '/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/';

    /**
     * The grammar of a namespace: identifier segments joined by single separators.
     */
    private const string NAMESPACE_PATTERN = '/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*(\\\[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)*$/';

    /**
     * Names PHP resolves itself, which therefore have no declaration in any codebase.
     */
    private const array BUILTIN_NAMES = [
        'self', 'static', 'parent',
        'int', 'string', 'float', 'bool', 'array', 'iterable', 'callable',
        'void', 'object', 'mixed', 'null', 'false', 'true', 'never',
    ];

    /**
     * The namespace part, empty when the name is not namespaced.
     */
    public string $namespace;

    /**
     * The last segment of the name.
     */
    public string $shortName;

    /**
     * @example Splitting a namespaced name
     *     (new \App\Analyzer\Graph\QualifiedName('App\\Domain\\Invoice'))->namespace // => 'App\\Domain'
     * @example The short name is the last segment
     *     (new \App\Analyzer\Graph\QualifiedName('App\\Domain\\Invoice'))->shortName // => 'Invoice'
     * @example A global name has no namespace
     *     (new \App\Analyzer\Graph\QualifiedName('Invoice'))->namespace // => ''
     *
     * @param string $fullName The name as analysis reported it
     */
    public function __construct(
        public string $fullName,
    ) {
        $lastSeparator = strrpos($fullName, '\\');
        $this->namespace = $lastSeparator === false ? '' : substr($fullName, 0, $lastSeparator);
        $this->shortName = $lastSeparator === false ? $fullName : substr($fullName, $lastSeparator + 1);
    }

    /**
     * Reports whether a string is a name PHP would accept for a single symbol.
     *
     * A class, method, property, function, constant and enum case are each named by
     * one segment, and every identifier built from them has the same precondition on
     * it. The rule is a property of PHP names rather than of any one identifier, so
     * it is asked here instead of being restated wherever a name is taken in.
     *
     * @param string $value The name to check
     *
     * @example A written symbol name is admissible
     *     \App\Analyzer\Graph\QualifiedName::isIdentifier('totalAmount') // => true
     * @example A qualified name is not one segment
     *     \App\Analyzer\Graph\QualifiedName::isIdentifier('App\\Domain\\Invoice') // => false
     * @example Nothing is not a name
     *     \App\Analyzer\Graph\QualifiedName::isIdentifier('') // => false
     *
     * @return bool True when PHP would accept the string as an identifier
     */
    public static function isIdentifier(string $value): bool
    {
        return $value !== '' && preg_match(self::IDENTIFIER_PATTERN, $value) === 1;
    }

    /**
     * Reports whether a string is a namespace PHP would accept.
     *
     * @param string $value The namespace to check, without a leading separator
     *
     * @example A namespace is identifier segments joined by separators
     *     \App\Analyzer\Graph\QualifiedName::isNamespace('App\\Domain\\Billing') // => true
     * @example The global namespace is written as no namespace at all
     *     \App\Analyzer\Graph\QualifiedName::isNamespace('') // => false
     *
     * @return bool True when PHP would accept the string as a namespace
     */
    public static function isNamespace(string $value): bool
    {
        return $value !== '' && preg_match(self::NAMESPACE_PATTERN, $value) === 1;
    }

    /**
     * Reports whether this name is one PHP resolves on its own.
     *
     * A builtin type, and the `self`, `static` and `parent` keywords, name nothing
     * a codebase declares, so no edge should be drawn to them.
     *
     * @example A builtin type names nothing a codebase declares
     *     (new \App\Analyzer\Graph\QualifiedName('int'))->isBuiltinType() // => true
     * @example A declared class does
     *     (new \App\Analyzer\Graph\QualifiedName('App\\Domain\\Invoice'))->isBuiltinType() // => false
     *
     * @return bool True when the name is builtin
     */
    public function isBuiltinType(): bool
    {
        return in_array(strtolower($this->fullName), self::BUILTIN_NAMES, true);
    }
}
