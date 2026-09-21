<?php

declare(strict_types=1);

namespace Tests\Unit\Action\Query;

use App\Action\Query\QueryActionInput;
use App\Analyzer\Graph\Direction;
use App\Config\AnalyzerKind;
use App\Config\Config;
use App\Config\ConfigException;
use App\Config\DebugAnalyzerConfig;
use App\Config\OutputFormat;
use App\Config\PhpVersion;
use App\Config\RawConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(QueryActionInput::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigException::class)]
#[UsesClass(RawConfig::class)]
#[UsesClass(AnalyzerKind::class)]
#[UsesClass(OutputFormat::class)]
#[UsesClass(Direction::class)]
#[UsesClass(DebugAnalyzerConfig::class)]
#[UsesClass(PhpVersion::class)]
#[Small]
final class QueryActionInputTest extends TestCase
{
    /**
     * @throws ConfigException
     */
    public function testAnInputCarriesTheQueryAsTheReaderWroteIt(): void
    {
        $config = Config::fromArray(['basePath' => '.', 'direction' => 'uses', 'type' => 'native']);

        self::assertSame('RETURN 1 AS n', (new QueryActionInput($config, 'RETURN 1 AS n'))->query);
    }

    /**
     * @throws ConfigException
     */
    public function testAnInputCarriesTheConfigurationTheGraphIsBuiltFrom(): void
    {
        $config = Config::fromArray(['basePath' => '.', 'direction' => 'uses', 'type' => 'native']);

        self::assertSame($config, (new QueryActionInput($config, 'RETURN 1 AS n'))->config);
    }
}
