<?php

declare(strict_types=1);

namespace App\Config;

use BackedEnum;

/**
 * Configuration data as a source reported it, read one field at a time.
 *
 * A configuration reader knows how its own source spells things — an environment
 * variable is always a string, a YAML scalar is already typed, a console option
 * may be absent. None of them know what the application needs a field to be. This
 * type is where that decision is made, once: every accessor states the type it
 * requires, converts a value that can honestly be read as that type, and raises a
 * ConfigException naming the field and what was found when it cannot.
 *
 * Keeping the conversion here rather than in each reader is what lets the readers
 * stay pass-through, and what makes "level must be a positive integer" one rule
 * rather than one rule per source.
 *
 * @phpstan-import-type ConfigField from ConfigReader
 * @phpstan-import-type ConfigFields from ConfigReader
 *
 * @visibility namespace
 */
final class RawConfig
{
    /**
     * @param ConfigFields $values The field values a source reported
     */
    public function __construct(
        private readonly array $values,
    ) {}

    /**
     * Reports whether the source mentioned a field at all.
     *
     * @param string $key The field name
     *
     * @return bool True when the field is present, even if its value is null
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * Reads a field that must be a non-empty string.
     *
     * @param string $key The field name
     *
     * @return string The field value
     *
     * @throws ConfigException If the field is missing, not a string, or empty
     */
    public function requiredString(string $key): string
    {
        $value = $this->values[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new ConfigException(sprintf(
                'Invalid configuration "%s": expected a non-empty string, got %s.',
                $key,
                self::describe($value),
            ));
        }

        return $value;
    }

    /**
     * Reads a field that must be a positive integer.
     *
     * @param string $key The field name
     *
     * @return int The field value
     *
     * @throws ConfigException If the field is missing or is not a positive integer
     */
    public function requiredPositiveInt(string $key): int
    {
        $value = $this->optionalPositiveInt($key);
        if ($value === null) {
            throw new ConfigException(sprintf(
                'Invalid configuration "%s": expected a positive integer, got nothing.',
                $key,
            ));
        }

        return $value;
    }

    /**
     * Reads an optional field that must be a positive integer when present.
     *
     * @param string $key The field name
     *
     * @return null|int The field value, or null when the source left it unset
     *
     * @throws ConfigException If the field is present but is not a positive integer
     */
    public function optionalPositiveInt(string $key): ?int
    {
        $value = $this->optionalInt($key);
        if ($value !== null && $value <= 0) {
            throw new ConfigException(sprintf(
                'Invalid configuration "%s": expected a positive integer, got %d.',
                $key,
                $value,
            ));
        }

        return $value;
    }

    /**
     * Reads an optional field that must be an integer when present.
     *
     * A numeric string counts as an integer, because an environment variable and a
     * console option have no other way to carry one.
     *
     * @param string $key The field name
     *
     * @return null|int The field value, or null when the source left it unset
     *
     * @example A source that can only carry text still reports a number
     *     (new \App\Config\RawConfig(['level' => '3']))->optionalInt('level') // => 3
     * @example A setting the source left out reads as nothing
     *     (new \App\Config\RawConfig([]))->optionalInt('level') // => null
     * @example Text that is not a number is a configuration error
     *     (new \App\Config\RawConfig(['level' => 'deep']))->optionalInt('level') // throws \App\Config\ConfigException: level
     *
     * @throws ConfigException If the field is present but cannot be read as an integer
     */
    public function optionalInt(string $key): ?int
    {
        $value = $this->values[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_INT);
            if ($parsed !== false) {
                return $parsed;
            }
        }

        throw new ConfigException(sprintf(
            'Invalid configuration "%s": expected an integer, got %s.',
            $key,
            self::describe($value),
        ));
    }

    /**
     * Reads an optional field that must be a list of non-empty strings.
     *
     * @param string $key The field name
     *
     * @return list<string> The field value, or an empty list when the source left it unset
     *
     * @throws ConfigException If the field is present but is not a list of non-empty strings
     */
    public function stringList(string $key): array
    {
        $value = $this->values[$key] ?? null;
        if ($value === null) {
            return [];
        }
        if (!is_array($value)) {
            throw new ConfigException(sprintf(
                'Invalid configuration "%s": expected a list of strings, got %s.',
                $key,
                self::describe($value),
            ));
        }

        $list = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new ConfigException(sprintf(
                    'Invalid configuration "%s": every entry must be a non-empty string, got %s.',
                    $key,
                    self::describe($item),
                ));
            }
            $list[] = $item;
        }

        return $list;
    }

    /**
     * Reads a field that must be one of a given set of enum cases.
     *
     * A closed type says which values exist; it does not say which of them this
     * installation can honour. Where the two differ — an analyzer whose engine this
     * build does not carry — the field is read against the cases that can actually
     * be chosen, so the error names the choice the user has rather than the choice
     * the type allows.
     *
     * @template TCase of \BackedEnum
     *
     * @param string      $key     The field name
     * @param list<TCase> $allowed The cases the field may name
     *
     * @return TCase The matching case
     *
     * @throws ConfigException If the field is missing or names none of those cases
     */
    public function oneOf(string $key, array $allowed): BackedEnum
    {
        $value = $this->requiredString($key);
        foreach ($allowed as $case) {
            if ((string) $case->value === $value) {
                return $case;
            }
        }

        throw new ConfigException(sprintf(
            'Invalid configuration "%s": expected one of %s, got "%s".',
            $key,
            implode(', ', array_map(static fn (BackedEnum $case): string => (string) $case->value, $allowed)),
            $value,
        ));
    }

    /**
     * Reads a field that must be one of the values of a string-backed enum.
     *
     * @template TEnum of \BackedEnum
     *
     * @param string              $key       The field name
     * @param class-string<TEnum> $enumClass The enum whose values the field must name
     *
     * @return TEnum The matching enum case
     *
     * @throws ConfigException If the field is missing or names no case of that enum
     */
    public function enum(string $key, string $enumClass): BackedEnum
    {
        $value = $this->requiredString($key);
        $case = $enumClass::tryFrom($value);
        if ($case === null) {
            $allowed = array_map(
                static fn (BackedEnum $known): string => (string) $known->value,
                $enumClass::cases(),
            );

            throw new ConfigException(sprintf(
                'Invalid configuration "%s": expected one of %s, got "%s".',
                $key,
                implode(', ', $allowed),
                $value,
            ));
        }

        return $case;
    }

    /**
     * Reads a field holding a nested group of fields.
     *
     * A missing group reads as an empty one, so a group whose every field has a
     * default needs no entry in the source.
     *
     * @param string $key The field name
     *
     * @return self The nested fields
     *
     * @throws ConfigException If the field is present but is not a group of named fields
     */
    public function nested(string $key): self
    {
        $value = $this->values[$key] ?? null;
        if ($value === null) {
            return new self([]);
        }
        if (!is_array($value)) {
            throw new ConfigException(sprintf(
                'Invalid configuration "%s": expected a group of settings, got %s.',
                $key,
                self::describe($value),
            ));
        }

        $nested = [];
        foreach ($value as $name => $item) {
            if (!is_string($name)) {
                throw new ConfigException(sprintf(
                    'Invalid configuration "%s": every setting must be named, got the key %s.',
                    $key,
                    self::describe($name),
                ));
            }
            $nested[$name] = $item;
        }

        return new self($nested);
    }

    /**
     * Describes a rejected value for an error message without dumping its contents.
     *
     * @param null|ConfigField $value The value that failed a check
     *
     * @return string A short description naming the type and, for scalars, the value
     */
    public static function describe(array|bool|float|int|string|null $value): string
    {
        if (is_string($value)) {
            return sprintf('the string "%s"', $value);
        }
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return sprintf('%s (%s)', var_export($value, true), get_debug_type($value));
        }

        return get_debug_type($value);
    }
}
