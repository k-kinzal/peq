<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer;

/**
 * One method a trait offers a class, and whether it offers a body with it.
 *
 * A trait can offer a method in two quite different senses. It can write one, which
 * the using class then has; or it can write only a signature, which is a demand that
 * the class have one from somewhere else. Telling the two apart is what decides
 * whether a trait's copy of a method is the copy the class ends up with, so the
 * distinction travels with the name rather than being looked up again later.
 *
 * @visibility namespace
 */
final class TraitMethod
{
    /**
     * @param string $declaringTrait Fully qualified name of the trait that writes it
     * @param bool   $demanded       True when the trait only demands the method rather than writing one
     */
    public function __construct(
        public readonly string $declaringTrait,
        public readonly bool $demanded,
    ) {}
}
