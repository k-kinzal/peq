<?php

declare(strict_types=1);

namespace Tests\Fixture\Config;

use App\Config\ConfigException;
use App\Config\YamlConfigLoader;
use Eris\Generator\AssociativeArrayGenerator;
use Eris\Generator\OneOfGenerator;
use Eris\Generator\SequenceGenerator;
use Eris\Generators;

/**
 * Whatever a YAML file might parse to, drawn at random.
 *
 * The configuration reader is the one place that faces contents nobody validated,
 * so the values it is checked against are drawn rather than chosen: scalars, lists,
 * groups, and the shapes no setting is written as — a group inside a group, a list
 * of groups, an unnamed key. A reader that is total over this domain is one that
 * cannot be made to fail in a way a user cannot act on.
 */
final class ParsedYaml
{
    /**
     * Draws a value of the kind YAML parsing produces.
     *
     * @return OneOfGenerator<mixed> The generator, drawing values of the kinds YAML holds
     */
    public static function anyValue(): OneOfGenerator
    {
        return new OneOfGenerator([
            self::scalar(),
            new SequenceGenerator(self::scalar()),
            new AssociativeArrayGenerator(['depth' => self::scalar(), 'seed' => self::scalar()]),
            new AssociativeArrayGenerator(['nested' => new AssociativeArrayGenerator(['deep' => self::scalar()])]),
            new SequenceGenerator(new AssociativeArrayGenerator(['name' => Generators::elements(['a', 'b'])])),
        ]);
    }

    /**
     * Draws the top level of a parsed file, which is usually but not always a map.
     *
     * @return OneOfGenerator<mixed> The generator, drawing a whole parsed document
     */
    public static function anyDocument(): OneOfGenerator
    {
        return new OneOfGenerator([
            new AssociativeArrayGenerator(['basePath' => self::anyValue(), 'debug' => self::anyValue()]),
            new SequenceGenerator(self::anyValue()),
            self::scalar(),
        ]);
    }

    /**
     * Reports what the reader made of parsed contents, settings or refusal alike.
     *
     * A property about reading twice has to compare both outcomes, and a refusal is
     * an outcome: describing it as its message is what lets the two be compared
     * without the property having to expect one of them in advance.
     *
     * @param YamlConfigLoader $loader The reader to ask
     * @param mixed            $parsed What the file described
     *
     * @return array<string, mixed>|string The settings, or the refusal message
     */
    public static function readingOf(YamlConfigLoader $loader, mixed $parsed): array|string
    {
        try {
            return $loader->settings($parsed);
        } catch (ConfigException $refusal) {
            return $refusal->getMessage();
        }
    }

    /**
     * Draws a single value of the kinds YAML can hold.
     *
     * @return OneOfGenerator<mixed> The generator, drawing one value
     */
    public static function scalar(): OneOfGenerator
    {
        return new OneOfGenerator([
            Generators::elements(['uses', 'used-by', 'phpstan', 'debug', '']),
            Generators::choose(-5, 5),
            Generators::elements([true, false]),
            Generators::constant(null),
            Generators::elements([1.5, -0.25]),
        ]);
    }
}
