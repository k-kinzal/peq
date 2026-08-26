<?php

declare(strict_types=1);

namespace App\Config;

/**
 * A source of configuration settings.
 *
 * Implementations read settings from wherever they live — a YAML file, the
 * environment, the command line, the defaults compiled into peq — and report them
 * under the names the application knows them by. The ConfigLoader consults several
 * of them in order and overlays what each one reports on what came before.
 *
 * A source reports settings, not arbitrary data: a value is a single setting, a
 * list of settings, or a named group of them. A source that faces input it does not
 * control is the one place where that has to be checked, because it is the only
 * place that knows what it read and where it read it from.
 *
 * @phpstan-type ConfigLeaf bool|float|int|string|null
 * @phpstan-type ConfigGroup array<string, ConfigLeaf|list<ConfigLeaf>>
 * @phpstan-type ConfigField ConfigLeaf|list<ConfigLeaf>|ConfigGroup
 * @phpstan-type ConfigFields array<string, ConfigField>
 */
interface ConfigReader
{
    /**
     * Reports the settings this source carries.
     *
     * A source that carries nothing reports no settings rather than failing, so a
     * project needs only the sources it actually uses.
     *
     * @return ConfigFields The settings, keyed by the names the application uses
     *
     * @throws ConfigException If the source exists but cannot be read as settings
     */
    public function read(): array;
}
