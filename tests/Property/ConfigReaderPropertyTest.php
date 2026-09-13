<?php

declare(strict_types=1);

namespace Tests\Property;

use App\Config\YamlConfigLoader;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\Config\ParsedYaml;

/**
 * The configuration reader, checked against contents nobody wrote for it.
 *
 * The reader is the boundary between a file someone edited by hand and the typed
 * settings the rest of peq works on, so the property that matters is totality: for
 * any parsed contents at all it either reports settings of the shape it promises or
 * raises a configuration error naming what it found. Anything else — a TypeError, a
 * warning, a value of a shape nothing downstream expects — is a failure of the
 * boundary rather than of the file.
 *
 * @group pbt
 *
 * @internal
 */
#[CoversClass(YamlConfigLoader::class)]
#[Group('pbt')]
#[Medium]
final class ConfigReaderPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Checks that parsed contents are read as settings or rejected as configuration.
     */
    public function testAnyParsedContentsAreEitherReadAsSettingsOrRejected(): void
    {
        $this->forAll(ParsedYaml::anyDocument())
            ->then(static function (array|bool|float|int|string|null $parsed): void {
                $reading = ParsedYaml::readingOf(new YamlConfigLoader('/unused'), $parsed);
                if (is_string($reading)) {
                    self::assertStringContainsString('configuration', $reading);

                    return;
                }

                foreach ($reading as $name => $value) {
                    self::assertIsString($name);
                    self::assertTrue(
                        $value === null || is_scalar($value) || is_array($value),
                        'A setting is a value, a list of values, or a group of them.',
                    );
                }
            })
        ;
    }

    /**
     * Checks that reading the same contents twice reports the same thing.
     */
    public function testReadingTheSameContentsTwiceReportsTheSameThing(): void
    {
        $this->forAll(ParsedYaml::anyDocument())
            ->then(static function (array|bool|float|int|string|null $parsed): void {
                $loader = new YamlConfigLoader('/unused');

                self::assertSame(
                    ParsedYaml::readingOf($loader, $parsed),
                    ParsedYaml::readingOf($loader, $parsed),
                );
            })
        ;
    }
}
