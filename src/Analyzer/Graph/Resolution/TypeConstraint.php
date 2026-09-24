<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Resolution;

use App\Analyzer\Graph\QualifiedName;

/**
 * A declared object type, retaining union alternatives and intersection requirements.
 */
final readonly class TypeConstraint
{
    /**
     * @param list<list<string>> $alternatives
     */
    public function __construct(public array $alternatives) {}

    /**
     * Reads declared object types while retaining union and intersection semantics.
     */
    public static function of(?string $text): self
    {
        $alternatives = [];
        foreach (explode('|', $text ?? '') as $union) {
            $members = [];
            foreach (explode('&', trim($union, '()? ')) as $name) {
                $name = ltrim(trim($name), '\\');
                if ($name !== '' && !(new QualifiedName($name))->isBuiltinType()) {
                    $members[] = $name;
                }
            }
            if ($members !== []) {
                $alternatives[] = $members;
            }
        }

        return new self($alternatives);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_unique(array_merge([], ...$this->alternatives)));
    }

    /**
     * Checks every requirement of at least one union alternative.
     */
    public function accepts(string $class, ClassHierarchy $hierarchy): bool
    {
        foreach ($this->alternatives as $members) {
            $accepted = true;
            foreach ($members as $member) {
                $accepted = $accepted && $hierarchy->isSubtype($class, $member);
            }
            if ($accepted) {
                return true;
            }
        }

        return false;
    }
}
