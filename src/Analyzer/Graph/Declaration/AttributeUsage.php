<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Declaration;

/**
 * One attribute written on a declaration.
 *
 * The graph already records an attribute as a relation, because writing one is a
 * dependency on the class it names. That relation answers "what breaks if this
 * attribute class changes"; it does not answer "which methods are routes", which is
 * the question a framework-shaped codebase actually asks. Recording the attribute on
 * the declaration as well is what makes the second question one lookup instead of a
 * walk through the relations of every candidate.
 *
 * The arguments are kept as they are written, not evaluated. `#[Route('/users')]` is
 * recognised by the text between its parentheses, and evaluating it would need a
 * runtime this analysis deliberately does not have.
 */
final class AttributeUsage
{
    /**
     * @param string       $name      The fully qualified name of the attribute class
     * @param list<string> $arguments The arguments as they are written, in source order
     */
    public function __construct(
        public readonly string $name,
        public readonly array $arguments = [],
    ) {
        assert($this->name !== '', 'An attribute must name a class');
    }

    /**
     * Writes the attribute the way its declaration writes it.
     *
     * @example An attribute with arguments reads as it was written
     *     (new \App\Analyzer\Graph\Declaration\AttributeUsage('Route', ["'/users'"]))->toString() // => "Route('/users')"
     * @example An attribute without arguments carries no parentheses
     *     (new \App\Analyzer\Graph\Declaration\AttributeUsage('Override'))->toString() // => 'Override'
     *
     * @return string The attribute as source code writes it, without its leading marker
     */
    public function toString(): string
    {
        if ($this->arguments === []) {
            return $this->name;
        }

        return $this->name.'('.implode(', ', $this->arguments).')';
    }
}
