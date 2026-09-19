<?php

declare(strict_types=1);

namespace App\Analyzer\NativeAnalyzer;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\TraitUseAdaptation\Alias;
use PhpParser\Node\Stmt\TraitUseAdaptation\Precedence;

/**
 * Which copy of a method a class taking traits on ends up with.
 *
 * A trait is not a symbol its methods belong to: the methods belong to every class
 * that uses it, and the graph records them there. Reading a trait's statements once
 * per using class is therefore the right thing to do — but only for the copies that
 * survive PHP's own rules. A class that writes a method keeps its own; two traits
 * offering the same method are settled by an `insteadof`; a method renamed by an `as`
 * is taken on under the new name and only under that one; and a trait that demands a
 * method rather than writing one is answered by whatever does write it, which may be
 * another trait or a class further up.
 *
 * Reading a copy PHP discards would record a method the class does not have, at the
 * line of a trait rather than of the class, which is why this is decided before a
 * trait statement is read rather than after.
 *
 * @visibility namespace
 */
final class TraitFlattening
{
    /**
     * Reads the renames one `use` statement applies to one trait.
     *
     * An adaptation naming no trait applies to every trait of the statement; one
     * naming a trait applies only to that trait.
     *
     * @param TraitUse $use       The `use` statement met while walking
     * @param string   $traitName Fully qualified name of the trait being read
     *
     * @return array<string, string> The new name of each renamed method, keyed by its lower-cased written name
     */
    public static function renames(TraitUse $use, string $traitName): array
    {
        $renames = [];
        foreach ($use->adaptations as $adaptation) {
            if (!$adaptation instanceof Alias || $adaptation->newName === null) {
                continue;
            }
            if ($adaptation->trait !== null && strtolower($adaptation->trait->toString()) !== strtolower($traitName)) {
                continue;
            }
            $renames[strtolower($adaptation->method->toString())] = $adaptation->newName->toString();
        }

        return $renames;
    }

    /**
     * Reports whether a class takes a trait's copy of a method on, or discards it.
     *
     * @param ClassLike   $class      The class-like reading the trait
     * @param ClassMethod $method     The method as the trait writes it
     * @param string      $methodName The name it is taken on as, after any rename
     * @param string      $traitName  Fully qualified name of the trait the copy is written in
     * @param SourceIndex $index      What the analysed files declare
     *
     * @return bool True when this copy is the one the class ends up with
     */
    public static function keepsMethod(ClassLike $class, ClassMethod $method, string $methodName, string $traitName, SourceIndex $index): bool
    {
        if (self::writesMethod($class, $methodName)) {
            return false;
        }

        $provider = self::providerOf($class, $methodName, $index);
        if ($provider === null || strtolower($provider) !== strtolower($traitName)) {
            return false;
        }

        return $method->stmts !== null || !self::inherits($class, $methodName, $index, []);
    }

    /**
     * Names the trait a class ends up taking a method from.
     *
     * A trait that writes the method answers for it before one that only demands it,
     * whatever order they are used in, because a demand is not an answer.
     *
     * @param ClassLike   $class      The class-like reading the traits
     * @param string      $methodName The method name, after any rename
     * @param SourceIndex $index      What the analysed files declare
     *
     * @return null|string The trait the method comes from, or null when no trait offers it
     */
    public static function providerOf(ClassLike $class, string $methodName, SourceIndex $index): ?string
    {
        $offers = self::offersOf($class, $methodName, $index);
        foreach ($offers as $offered) {
            if (!$offered->demanded) {
                return $offered->declaringTrait;
            }
        }

        return $offers === [] ? null : $offers[0]->declaringTrait;
    }

    /**
     * Reads every offer of a method the traits a class uses make, in the order they are used.
     *
     * An `insteadof` settles which trait a method is taken from, so the offers of
     * every other trait of that name are not offers at all.
     *
     * @param ClassLike   $class      The class-like reading the traits
     * @param string      $methodName The method name, after any rename
     * @param SourceIndex $index      What the analysed files declare
     *
     * @return list<TraitMethod> The offers that stand, in the order the traits are used
     */
    public static function offersOf(ClassLike $class, string $methodName, SourceIndex $index): array
    {
        $wanted = strtolower($methodName);
        $settled = null;
        $offers = [];

        foreach ($class->getTraitUses() as $use) {
            foreach ($use->adaptations as $adaptation) {
                if ($adaptation instanceof Precedence && $adaptation->trait !== null && strtolower($adaptation->method->toString()) === $wanted) {
                    $settled = strtolower($adaptation->trait->toString());
                }
            }
            foreach ($use->traits as $trait) {
                $through = $trait->toString();
                $offered = self::methodsOf($through, self::renames($use, $through), $index, [])[$wanted] ?? null;
                if ($offered !== null && ($settled === null || strtolower($through) === $settled)) {
                    $offers[] = $offered;
                }
            }
        }

        return $offers;
    }

    /**
     * Reports whether a class-like writes a method of the given name itself.
     *
     * @param ClassLike $class      The class-like being read
     * @param string    $methodName The method name, in any casing
     *
     * @return bool True when the declaration writes that method
     */
    public static function writesMethod(ClassLike $class, string $methodName): bool
    {
        return self::methodWritten($class, $methodName) !== null;
    }

    /**
     * Returns the method of that name a class-like writes, when it writes one.
     *
     * @param ClassLike $class      The class-like being read
     * @param string    $methodName The method name, in any casing
     *
     * @return null|ClassMethod The method it writes, or null when it writes none
     */
    public static function methodWritten(ClassLike $class, string $methodName): ?ClassMethod
    {
        $wanted = strtolower($methodName);
        foreach ($class->stmts as $statement) {
            if ($statement instanceof ClassMethod && strtolower($statement->name->toString()) === $wanted) {
                return $statement;
            }
        }

        return null;
    }

    /**
     * Reports whether a class has a written method of that name from further up.
     *
     * A trait that demands a method is answered by anything that writes one, and a
     * class that inherits a written method has already answered. Only what the
     * analysed files declare can be looked up; a class whose parent lives outside
     * them has nothing to be read here, which is also all the reference engine can
     * say about it.
     *
     * @param ClassLike    $class      The class-like being read
     * @param string       $methodName The method name, after any rename
     * @param SourceIndex  $index      What the analysed files declare
     * @param list<string> $seen       The ancestors already read, which stops a cycle
     *
     * @return bool True when something above the class writes that method
     */
    public static function inherits(ClassLike $class, string $methodName, SourceIndex $index, array $seen): bool
    {
        $parentName = $class instanceof Class_ && $class->extends !== null ? $class->extends->toString() : null;
        if ($parentName === null || in_array(strtolower($parentName), $seen, true)) {
            return false;
        }
        $seen[] = strtolower($parentName);

        $parent = $index->classLike($parentName);
        if ($parent === null) {
            return false;
        }

        $written = self::methodWritten($parent->node, $methodName);
        if ($written !== null) {
            return $written->stmts !== null;
        }

        $provider = self::providerOf($parent->node, $methodName, $index);
        foreach (self::offersOf($parent->node, $methodName, $index) as $offered) {
            if ($offered->declaringTrait === $provider && !$offered->demanded) {
                return true;
            }
        }

        return self::inherits($parent->node, $methodName, $index, $seen);
    }

    /**
     * Reads the methods a trait offers, following the traits it uses in turn.
     *
     * @param string                $traitName Fully qualified name of the trait
     * @param array<string, string> $renames   The renames the using statement applies to it
     * @param SourceIndex           $index     What the analysed files declare
     * @param list<string>          $reading   The traits already being read, which stops a cycle
     *
     * @return array<string, TraitMethod> What the trait offers, keyed by lower-cased method name
     */
    public static function methodsOf(string $traitName, array $renames, SourceIndex $index, array $reading): array
    {
        $declaration = $index->classLike($traitName);
        if ($declaration === null || in_array(strtolower($traitName), $reading, true)) {
            return [];
        }
        $reading[] = strtolower($traitName);

        $methods = [];
        foreach ($declaration->node->stmts as $statement) {
            if ($statement instanceof ClassMethod) {
                $written = strtolower($statement->name->toString());
                $methods[strtolower($renames[$written] ?? $written)] = new TraitMethod($declaration->name, $statement->stmts === null);
            }
        }

        foreach ($declaration->node->getTraitUses() as $use) {
            foreach ($use->traits as $nested) {
                $nestedName = $nested->toString();
                foreach (self::methodsOf($nestedName, self::renames($use, $nestedName), $index, $reading) as $name => $offered) {
                    $methods[$name] ??= $offered;
                }
            }
        }

        return $methods;
    }
}
