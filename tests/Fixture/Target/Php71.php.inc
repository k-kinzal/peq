<?php

namespace Tests\Fixture\Target\Php71;

use RuntimeException;
use LogicException;

class Reader
{
    private const MODE = 'read';

    public function initial(?string $text): ?string
    {
        return $text === null ? null : $text{0};
    }

    public function describe(?string $text): string
    {
        try {
            return self::MODE . ':' . strtoupper((string) $this->initial($text));
        } catch (RuntimeException | LogicException $failure) {
            return self::MODE;
        }
    }
}
