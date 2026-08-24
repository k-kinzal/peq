<?php

declare(strict_types=1);

namespace Tests\Fixture\Source;

use RuntimeException;

interface Guard {}

class GuardedClass
{
    public function guarded(mixed $value): bool
    {
        try {
            return $value instanceof GuardedClass;
        } catch (RuntimeException $failure) {
            return false;
        }
    }

    public function guardedBy(Guard $guard): ?GuardedClass
    {
        return $guard instanceof Guard ? $this : null;
    }
}
