<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\EnvConfigReader;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(EnvConfigReader::class)]
#[Small]
final class EnvConfigReaderTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        putenv('PEQ_TEST_BASE_PATH');
        putenv('PEQ_TEST_DIRECTION');
        putenv('PEQ_TEST_LEVEL');
        putenv('PEQ_TEST_OUTPUT');
        putenv('PEQ_TEST_EXCLUDES');
        putenv('PEQ_TEST_INCLUDES');
        putenv('PEQ_TEST_DEBUG_DEPTH');
        putenv('PEQ_TEST_DEBUG_SEED');
        putenv('PEQ_TEST_');
        putenv('OTHER_TEST_BASE_PATH');

        parent::tearDown();
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadTurnsAScreamingSnakeCaseNameIntoTheSettingItNames(): void
    {
        putenv('PEQ_TEST_BASE_PATH=/env/path');

        self::assertSame('/env/path', (new EnvConfigReader('PEQ_TEST_'))->read()['basePath'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsANumberAsTheTextTheEnvironmentHolds(): void
    {
        putenv('PEQ_TEST_LEVEL=10');

        self::assertSame('10', (new EnvConfigReader('PEQ_TEST_'))->read()['level'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsTheFormatTheEnvironmentAsksFor(): void
    {
        putenv('PEQ_TEST_OUTPUT=dot');

        self::assertSame('dot', (new EnvConfigReader('PEQ_TEST_'))->read()['output'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadSplitsACommaSeparatedListIntoItsEntries(): void
    {
        putenv('PEQ_TEST_EXCLUDES=vendor, build ,tests');

        self::assertSame(['vendor', 'build', 'tests'], (new EnvConfigReader('PEQ_TEST_'))->read()['excludes'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadTurnsAnEmptyListIntoNoEntriesRatherThanOneEmptyEntry(): void
    {
        putenv('PEQ_TEST_INCLUDES=');

        self::assertSame([], (new EnvConfigReader('PEQ_TEST_'))->read()['includes'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsDebugVariablesInsideTheDebugGroup(): void
    {
        putenv('PEQ_TEST_DEBUG_DEPTH=9');
        putenv('PEQ_TEST_DEBUG_SEED=42');

        self::assertSame(['depth' => '9', 'seed' => '42'], (new EnvConfigReader('PEQ_TEST_'))->read()['debug'] ?? null);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadLeavesTheDebugGroupOutWhenNoDebugVariableIsSet(): void
    {
        putenv('PEQ_TEST_DIRECTION=used-by');

        self::assertArrayNotHasKey('debug', (new EnvConfigReader('PEQ_TEST_'))->read());
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadIgnoresVariablesWithoutTheConfiguredPrefix(): void
    {
        putenv('OTHER_TEST_BASE_PATH=/other');
        putenv('PEQ_TEST_BASE_PATH=/mine');

        $config = (new EnvConfigReader('PEQ_TEST_'))->read();

        self::assertSame('/mine', $config['basePath'] ?? null);
        self::assertArrayNotHasKey('otherTestBasePath', $config);
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadIgnoresAVariableThatIsNothingButThePrefix(): void
    {
        putenv('PEQ_TEST_=stray');

        self::assertArrayNotHasKey('', (new EnvConfigReader('PEQ_TEST_'))->read());
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsNothingWhenNoVariableCarriesThePrefix(): void
    {
        self::assertSame([], (new EnvConfigReader('PEQ_TEST_ABSENT_'))->read());
    }

    /**
     * @throws \App\Config\ConfigException
     */
    public function testReadReportsEverySettingTheEnvironmentHolds(): void
    {
        putenv('PEQ_TEST_BASE_PATH=/env/path');
        putenv('PEQ_TEST_LEVEL=10');

        $config = (new EnvConfigReader('PEQ_TEST_'))->read();

        self::assertSame('/env/path', $config['basePath'] ?? null);
        self::assertSame('10', $config['level'] ?? null);
    }
}
