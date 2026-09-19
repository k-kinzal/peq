<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Declaration;

/**
 * What a callable declaration promises about how it is called and what it gives back.
 *
 * A signature is the part of a method or function that other code is written against,
 * so it is the part a change to it can break. Holding the parameters and the return
 * type together — rather than as loose fields on the node — is what lets the question
 * "which callables take this shape" be asked of one value.
 *
 * Types are kept as they were written. A written type is what a reader recognises and
 * what a query is written against; resolving it would answer a different question
 * than the one the source asked.
 */
final class Signature
{
    /**
     * @param list<Parameter> $parameters The parameters, in the order they are declared
     * @param null|string     $returnType The declared return type as written, or null when none was written
     */
    public function __construct(
        public readonly array $parameters = [],
        public readonly ?string $returnType = null,
    ) {}

    /**
     * Writes the signature the way its declaration writes it.
     *
     * @example A signature reads as source code writes it
     *     $amount = new \App\Analyzer\Graph\Declaration\Parameter('amount', 'int');
     *     (new \App\Analyzer\Graph\Declaration\Signature([$amount], 'void'))->toString() // => '(int $amount): void'
     * @example A declaration that promises nothing about its result says nothing
     *     (new \App\Analyzer\Graph\Declaration\Signature())->toString() // => '()'
     *
     * @return string The signature as source code writes it
     */
    public function toString(): string
    {
        $written = array_map(static fn (Parameter $parameter): string => $parameter->toString(), $this->parameters);

        return '('.implode(', ', $written).')'.($this->returnType === null ? '' : ': '.$this->returnType);
    }

    /**
     * Lists the names of the parameters, in the order they are declared.
     *
     * @example The names are the ones a named argument would use
     *     $amount = new \App\Analyzer\Graph\Declaration\Parameter('amount', 'int');
     *     (new \App\Analyzer\Graph\Declaration\Signature([$amount]))->parameterNames() // => ['amount']
     *
     * @return list<string> The parameter names
     */
    public function parameterNames(): array
    {
        return array_map(static fn (Parameter $parameter): string => $parameter->name, $this->parameters);
    }

    /**
     * Lists the declared types of the parameters, in the order they are declared.
     *
     * A parameter written without a type is reported as an empty string rather than
     * being left out, so that the list stays aligned with the names beside it.
     *
     * @example An untyped parameter keeps its place in the list
     *     $value = new \App\Analyzer\Graph\Declaration\Parameter('value');
     *     (new \App\Analyzer\Graph\Declaration\Signature([$value]))->parameterTypes() // => ['']
     *
     * @return list<string> The declared parameter types, as written
     */
    public function parameterTypes(): array
    {
        return array_map(static fn (Parameter $parameter): string => $parameter->type ?? '', $this->parameters);
    }
}
