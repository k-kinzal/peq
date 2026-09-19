<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Declaration;

/**
 * Everything a declaration says about a symbol besides where it points.
 *
 * The graph answers what a change reaches. That answer is a set of symbols, and a
 * symbol on its own is a name — which is enough to draw a tree and not enough to
 * decide anything. Whether the method a change reaches is private or public, whether
 * it is a route, what it takes and what it gives back: those are the facts a reader
 * needs in order to act on the answer, and re-reading the source to get them defeats
 * the point of having analysed it once.
 *
 * So they are recorded on the node. Each field is optional in the honest sense: a
 * class has no signature, a function has no visibility, and a symbol analysis only
 * ever saw referenced has none of them. Absence here means "the source says nothing",
 * never "the analysis did not look".
 */
final class SymbolDeclaration
{
    /**
     * @param null|Visibility      $visibility The visibility written on it, or null when its kind carries none
     * @param Modifiers            $modifiers  The keywords written on it besides its visibility
     * @param null|Signature       $signature  What it promises about being called, or null when it is not callable
     * @param list<AttributeUsage> $attributes The attributes written on it, in source order
     * @param null|string          $type       The declared type as written, or null when none was written
     * @param null|string          $value      The assigned value as written, or null when it assigns none
     * @param bool                 $deprecated Whether the declaration marks itself deprecated
     */
    public function __construct(
        public readonly ?Visibility $visibility = null,
        public readonly Modifiers $modifiers = new Modifiers(),
        public readonly ?Signature $signature = null,
        public readonly array $attributes = [],
        public readonly ?string $type = null,
        public readonly ?string $value = null,
        public readonly bool $deprecated = false,
    ) {}

    /**
     * Lists the fully qualified names of the attributes written on the declaration.
     *
     * @example The names are the ones the attribute classes are declared under
     *     $route = new \App\Analyzer\Graph\Declaration\AttributeUsage('App\\Http\\Route', ["'/users'"]);
     *     (new \App\Analyzer\Graph\Declaration\SymbolDeclaration(attributes: [$route]))->attributeNames() // => ['App\\Http\\Route']
     *
     * @return list<string> The attribute class names, in source order
     */
    public function attributeNames(): array
    {
        return array_map(static fn (AttributeUsage $usage): string => $usage->name, $this->attributes);
    }

    /**
     * Reports whether a named attribute is written on the declaration.
     *
     * The name is compared the way PHP resolves one: case-insensitively, and against
     * the fully qualified name, so a query may ask for an attribute without knowing
     * how the file that uses it happened to import it.
     *
     * @param string $name The fully qualified name of the attribute class
     *
     * @example An attribute is found under the name its class is declared with
     *     $route = new \App\Analyzer\Graph\Declaration\AttributeUsage('App\\Http\\Route');
     *     $declared = new \App\Analyzer\Graph\Declaration\SymbolDeclaration(attributes: [$route]);
     *     $declared->hasAttribute('app\\http\\route') // => true
     * @example An attribute that is not written is not found
     *     (new \App\Analyzer\Graph\Declaration\SymbolDeclaration())->hasAttribute('App\\Http\\Route') // => false
     *
     * @return bool True when the declaration carries that attribute
     */
    public function hasAttribute(string $name): bool
    {
        $wanted = strtolower(ltrim($name, '\\'));
        foreach ($this->attributes as $usage) {
            if (strtolower(ltrim($usage->name, '\\')) === $wanted) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports whether the declaration says anything worth recording.
     *
     * A symbol an edge merely referred to carries a declaration in name only: no
     * visibility, no keyword, no signature, nothing written on it. Telling that apart
     * from a declaration that was actually read is what lets the graph prefer the
     * description that came from the source over the one that came from a reference.
     *
     * @example A declaration read from nothing says nothing
     *     (new \App\Analyzer\Graph\Declaration\SymbolDeclaration())->empty() // => true
     * @example One visibility is enough to be worth keeping
     *     $seen = \App\Analyzer\Graph\Declaration\Visibility::Public;
     *     (new \App\Analyzer\Graph\Declaration\SymbolDeclaration(visibility: $seen))->empty() // => false
     *
     * @return bool True when every field is absent or empty
     */
    public function empty(): bool
    {
        return $this->visibility === null
            && $this->modifiers->none()
            && $this->signature === null
            && $this->attributes === []
            && $this->type === null
            && $this->value === null
            && !$this->deprecated;
    }
}
