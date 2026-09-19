<?php

declare(strict_types=1);

namespace Tests\Property;

use App\Config\ConfigException;
use App\Config\YamlConfigLoader;
use Eris\Generator\AssociativeArrayGenerator;
use Eris\Generator\OneOfGenerator;
use Eris\Generator\SequenceGenerator;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

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
 * Parsed YAML is a scalar, a list or a map, nested; the documents are drawn from
 * those shapes around the settings peq reads, with values taken from the words and
 * numbers a hand-edited file holds.
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
        $scalar = new OneOfGenerator([
            Generators::elements(['uses', 'used-by', 'phpstan', 'debug', '']),
            Generators::choose(-5, 5),
            Generators::elements([true, false]),
            Generators::constant(null),
            Generators::elements([1.5, -0.25]),
        ]);
        $value = new OneOfGenerator([
            $scalar,
            new SequenceGenerator($scalar),
            new AssociativeArrayGenerator(['depth' => $scalar, 'seed' => $scalar]),
            new AssociativeArrayGenerator(['nested' => new AssociativeArrayGenerator(['deep' => $scalar])]),
        ]);

        $this->limitTo(300)
            ->forAll(new OneOfGenerator([
                new AssociativeArrayGenerator(['basePath' => $value, 'debug' => $value, 'direction' => $value, 'level' => $value]),
                new SequenceGenerator($value),
                $scalar,
            ]))
            ->then(static function (array|bool|float|int|string|null $parsed): void {
                try {
                    $settings = (new YamlConfigLoader('/unused'))->settings($parsed);
                } catch (ConfigException $refusal) {
                    self::assertStringContainsString('configuration', $refusal->getMessage());

                    return;
                }

                self::assertSame(is_array($parsed) ? array_keys($parsed) : [], array_keys($settings), 'Every named setting is read under its own name, and nothing else is.');
            })
        ;
    }

    /**
     * Checks that reading the same contents twice reports the same thing.
     */
    public function testReadingTheSameContentsTwiceReportsTheSameThing(): void
    {
        $scalar = new OneOfGenerator([
            Generators::elements(['uses', 'used-by', 'phpstan', 'debug', '']),
            Generators::choose(-5, 5),
            Generators::elements([true, false]),
            Generators::constant(null),
        ]);

        $this->limitTo(300)
            ->forAll(new OneOfGenerator([
                new AssociativeArrayGenerator(['basePath' => $scalar, 'direction' => $scalar, 'debug' => new AssociativeArrayGenerator(['depth' => $scalar, 'seed' => $scalar])]),
                new SequenceGenerator($scalar),
                $scalar,
            ]))
            ->then(static function (array|bool|float|int|string|null $parsed): void {
                $loader = new YamlConfigLoader('/unused');

                try {
                    $first = $loader->settings($parsed);
                } catch (ConfigException $refusal) {
                    $first = $refusal->getMessage();
                }

                try {
                    $again = $loader->settings($parsed);
                } catch (ConfigException $refusal) {
                    $again = $refusal->getMessage();
                }

                self::assertSame($first, $again);
            })
        ;
    }
}
