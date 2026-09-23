<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\ExperimentalConfigReader;
use App\Config\InputConfigReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * @internal
 */
#[CoversClass(ExperimentalConfigReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(InputConfigReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\App\Config\RawConfig::class)]
final class ExperimentalConfigReaderTest extends TestCase
{
    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadForcesTheNativeSourceVersionRange(): void
    {
        self::assertSame(['type' => 'native'], (new ExperimentalConfigReader())->read());
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testCoordinateRejectsNonPositiveLines(): void
    {
        $input = new ArrayInput(['--line' => '0'], new InputDefinition([new InputOption('line', null, InputOption::VALUE_REQUIRED)]));
        $this->expectException(\App\Config\ConfigException::class);
        ExperimentalConfigReader::coordinate(new InputConfigReader($input), 'line');
    }
}
