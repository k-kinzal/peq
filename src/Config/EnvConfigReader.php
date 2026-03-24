<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Reads configuration from environment variables.
 *
 * This reader extracts configuration values from environment variables with a
 * specific prefix (default: 'PEQ_'). Variable names are converted from SCREAMING_SNAKE_CASE
 * to camelCase to match the Config class property names. This allows configuration
 * to be provided via environment variables in CI/CD pipelines or containerized environments.
 *
 * Array values (includes, excludes) use comma-separated format: PEQ_EXCLUDES=vendor,tests
 * Integer values (level) are automatically converted from strings.
 */
final class EnvConfigReader implements ConfigReader
{
    private const ARRAY_KEYS = ['includes', 'excludes'];

    private const INT_KEYS = ['level'];

    /**
     * @param string $prefix The prefix for environment variables to read (e.g., 'PEQ_')
     */
    public function __construct(
        private readonly string $prefix = 'PEQ_'
    ) {}

    /**
     * {@inheritdoc}
     */
    public function read(): array
    {
        $config = [];
        foreach (getenv() as $name => $value) {
            if (!str_starts_with($name, $this->prefix)) {
                continue;
            }

            $name = substr($name, strlen($this->prefix));
            $name = strtolower($name);

            if (str_starts_with($name, 'debug_')) {
                $subKey = substr($name, 6);
                if (!isset($config['debug']) || !is_array($config['debug'])) {
                    $config['debug'] = [];
                }
                $config['debug'][$subKey] = $this->castValue($subKey, $value);

                continue;
            }

            $name = preg_replace_callback('/_([a-z])/', function ($matches) {
                return strtoupper($matches[1]);
            }, $name);

            if (!is_string($name)) {
                continue;
            }

            $config[$name] = $this->castValue($name, $value);
        }

        return $config;
    }

    /**
     * @return int|list<string>|string
     */
    private function castValue(string $key, string $value): array|int|string
    {
        if (in_array($key, self::ARRAY_KEYS, true)) {
            return $value !== '' ? array_map('trim', explode(',', $value)) : [];
        }

        if (in_array($key, self::INT_KEYS, true)) {
            $int = filter_var($value, FILTER_VALIDATE_INT);

            return $int !== false ? $int : $value;
        }

        return $value;
    }
}
