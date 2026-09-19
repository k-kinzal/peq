<?php

declare(strict_types=1);

namespace App\Config;

use Override;

/**
 * Reads configuration from environment variables.
 *
 * Variables carrying the configured prefix (default `PEQ_`) are reported as
 * configuration settings, with `SCREAMING_SNAKE_CASE` names mapped to the setting
 * names the application uses and `PEQ_DEBUG_*` variables reported inside the debug
 * group. Lists use the comma-separated form an environment variable can carry:
 * `PEQ_EXCLUDES=vendor,tests`.
 *
 * Numbers are reported as the text the environment holds. Deciding whether that
 * text is a valid level or depth is not this reader's job — it belongs to the one
 * place that knows what each setting has to be, so that the same value is judged
 * the same way whichever source supplied it.
 *
 * @phpstan-import-type ConfigFields from ConfigReader
 */
final readonly class EnvConfigReader implements ConfigReader
{
    /**
     * Settings whose environment form is a comma-separated list.
     */
    private const array LIST_KEYS = ['includes', 'excludes'];

    /**
     * Name prefix marking a variable as belonging to the debug group.
     */
    private const string DEBUG_PREFIX = 'debug_';

    /**
     * @param string $prefix The prefix marking an environment variable as configuration
     */
    public function __construct(
        private string $prefix = 'PEQ_',
    ) {}

    /**
     * Reports the configuration the environment carries.
     *
     * @return ConfigFields The settings found, as the environment spells them
     */
    #[Override]
    public function read(): array
    {
        $config = [];
        $debug = [];

        foreach (getenv() as $name => $value) {
            if (!str_starts_with($name, $this->prefix)) {
                continue;
            }

            $key = strtolower(substr($name, strlen($this->prefix)));
            if ($key === '') {
                continue;
            }

            if (str_starts_with($key, self::DEBUG_PREFIX)) {
                $debug[substr($key, strlen(self::DEBUG_PREFIX))] = $value;

                continue;
            }

            $setting = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));

            $config[$setting] = in_array($setting, self::LIST_KEYS, true)
                ? ($value === '' ? [] : array_map('trim', explode(',', $value)))
                : $value;
        }

        if ($debug !== []) {
            $config['debug'] = $debug;
        }

        return $config;
    }
}
