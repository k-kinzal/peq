<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Declaration;

/**
 * One parameter of a callable declaration, as it is written.
 *
 * The graph already records the class-likes a signature names, because those are
 * dependencies. A parameter is not a dependency; it is part of the shape of the thing
 * that depends. Recording it separately is what lets a question be asked about the
 * shape — which handlers take a request, which methods are variadic, which parameters
 * were promoted into properties — without re-reading the source.
 *
 * The type is kept as it was written rather than resolved, because that is the form a
 * reader recognises and the form the question is usually asked in.
 */
final readonly class Parameter
{
    /**
     * @param string      $name     The parameter name, without its leading dollar sign
     * @param null|string $type     The declared type as written, or null when none was written
     * @param bool        $optional Whether the declaration gives it a default value
     * @param bool        $variadic Whether it collects the remaining arguments
     * @param bool        $byRef    Whether it is taken by reference
     * @param bool        $promoted Whether it also declares a property of the class
     */
    public function __construct(
        public string $name,
        public ?string $type = null,
        public bool $optional = false,
        public bool $variadic = false,
        public bool $byRef = false,
        public bool $promoted = false,
    ) {
        assert($this->name !== '', 'A parameter must be named');
    }

    /**
     * Writes the parameter the way its declaration writes it.
     *
     * The rendering is the source form minus the default value, because the default
     * is the one part of a parameter that says nothing about how it may be called.
     *
     * @example A typed parameter reads as it was written
     *     (new \App\Analyzer\Graph\Declaration\Parameter('amount', 'int'))->toString() // => 'int $amount'
     * @example A variadic parameter keeps the spread that makes it one
     *     (new \App\Analyzer\Graph\Declaration\Parameter('rows', 'string', variadic: true))->toString() // => 'string ...$rows'
     * @example An untyped parameter is just its name
     *     (new \App\Analyzer\Graph\Declaration\Parameter('value'))->toString() // => '$value'
     *
     * @return string The parameter as source code writes it
     */
    public function toString(): string
    {
        return ($this->type === null ? '' : $this->type.' ')
            .($this->byRef ? '&' : '')
            .($this->variadic ? '...' : '')
            .'$'.$this->name;
    }
}
