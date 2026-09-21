<?php

declare(strict_types=1);

namespace Tests\Unit\Action\Experimental;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(\App\Action\Experimental\InspectVariablesInput::class)]
final class InspectVariablesInputTest extends TestCase
{
    public function testInputKeepsTheExactOccurrenceSelection(): void
    {
        $config = new \App\Config\Config('.', \App\Analyzer\Graph\Direction::Uses);
        $input = new \App\Action\Experimental\InspectVariablesInput($config, 'Example::method', 10, 'value', 7);
        self::assertSame(10, $input->line);
        self::assertSame(7, $input->column);
        self::assertSame('value', $input->variable);
    }
}
