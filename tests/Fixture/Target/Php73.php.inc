<?php

namespace Tests\Fixture\Target\Php73;

class Report
{
    public function heading(string $title): string
    {
        return <<<TEXT
            # $title
            TEXT;
    }

    public function widest(int $left, int $right): int
    {
        return max(
            $left,
            $right,
            strlen($this->heading('report')),
        );
    }
}
