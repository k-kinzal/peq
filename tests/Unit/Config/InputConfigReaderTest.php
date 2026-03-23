<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\ConfigException;
use App\Config\InputConfigReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * @internal
 */
final class InputConfigReaderTest extends TestCase
{
    #[Test]
    public function testReadMergesOptionsAndArguments(): void
    {
        $definition = new InputDefinition([
            new InputArgument('target', InputArgument::OPTIONAL),
            new InputArgument('path', InputArgument::OPTIONAL),
            new InputOption('direction', null, InputOption::VALUE_REQUIRED),
            new InputOption('level', null, InputOption::VALUE_REQUIRED),
        ]);

        $input = new ArrayInput([
            'target' => 'App\Foo',
            'path' => '/project/path',
            '--direction' => 'used-by',
            '--level' => 5,
        ], $definition);

        $reader = new InputConfigReader($input);
        $config = $reader->read();

        self::assertSame('used-by', $config['direction']);
        self::assertSame(5, $config['level']);
        self::assertSame('App\Foo', $config['target']);
        self::assertSame('/project/path', $config['basePath']);
    }

    #[Test]
    public function testReadMapsPathToBasePath(): void
    {
        $definition = new InputDefinition([
            new InputArgument('path', InputArgument::REQUIRED),
        ]);

        $input = new ArrayInput([
            'path' => '/some/path',
        ], $definition);

        $reader = new InputConfigReader($input);
        $config = $reader->read();

        self::assertSame('/some/path', $config['basePath']);
    }

    #[Test]
    public function testReadMapsIncludeAndExcludeToPluralForm(): void
    {
        $definition = new InputDefinition([
            new InputOption('include', 'I', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED),
            new InputOption('exclude', 'E', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED),
        ]);

        $input = new ArrayInput([
            '--include' => ['src/**'],
            '--exclude' => ['vendor/**'],
        ], $definition);

        $reader = new InputConfigReader($input);
        $config = $reader->read();

        self::assertSame(['src/**'], $config['includes']);
        self::assertSame(['src/**'], $config['includes']);
        self::assertSame(['vendor/**'], $config['excludes']);
    }

    #[Test]
    public function testReadParsesDebugOptions(): void
    {
        $definition = new InputDefinition([
            new InputOption('debug-depth', null, InputOption::VALUE_REQUIRED),
            new InputOption('debug-seed', null, InputOption::VALUE_REQUIRED),
        ]);

        $input = new ArrayInput([
            '--debug-depth' => '5',
            '--debug-seed' => '123',
        ], $definition);

        $reader = new InputConfigReader($input);
        $config = $reader->read();

        self::assertArrayHasKey('debug', $config);

        /** @var array{depth: int, seed: int} $debug */
        $debug = $config['debug'];
        self::assertSame(5, $debug['depth']);
        self::assertSame(123, $debug['seed']);
    }

    #[Test]
    public function testReadReverseOptionSetsDirectionToUsedBy(): void
    {
        $definition = new InputDefinition([
            new InputOption('reverse', 'R', InputOption::VALUE_NONE),
            new InputOption('direction', 'D', InputOption::VALUE_REQUIRED, '', 'uses'),
        ]);

        $input = new ArrayInput([
            '--reverse' => true,
        ], $definition);

        $reader = new InputConfigReader($input);
        $config = $reader->read();

        self::assertSame('used-by', $config['direction']);
    }

    #[Test]
    public function testReadWithoutReverseDoesNotChangeDirection(): void
    {
        $definition = new InputDefinition([
            new InputOption('reverse', 'R', InputOption::VALUE_NONE),
            new InputOption('direction', 'D', InputOption::VALUE_REQUIRED, '', 'uses'),
        ]);

        $input = new ArrayInput([], $definition);

        $reader = new InputConfigReader($input);
        $config = $reader->read();

        self::assertSame('uses', $config['direction']);
    }

    #[Test]
    public function testReadValidatesLevel(): void
    {
        $definition = new InputDefinition([
            new InputOption('level', null, InputOption::VALUE_REQUIRED),
        ]);

        $input = new ArrayInput([
            '--level' => 'invalid',
        ], $definition);

        $reader = new InputConfigReader($input);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Invalid level: invalid. Level must be a positive integer.');

        $reader->read();

        $inputValid = new ArrayInput([
            '--level' => '5',
        ], $definition);
        $readerValid = new InputConfigReader($inputValid);
        $configValid = $readerValid->read();

        self::assertSame(5, $configValid['level']);
    }
}
