<?php

declare(strict_types=1);

namespace App\Config;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

/**
 * Represents the application's configuration settings.
 *
 * This value object holds all configuration parameters for the dependency analysis,
 * including the target path, analysis direction, depth limits, and file filtering rules.
 * Configuration values are immutable and validated upon creation to ensure consistency.
 */
final readonly class Config
{
    /**
     * @param string                   $basePath  The base path for the PHP project to analyze
     * @param string                   $direction Analysis direction: 'uses' (what target depends on)
     *                                            or 'used-by' (what depends on target)
     * @param null|int                 $level     Maximum depth of dependency traversal (null means unlimited)
     * @param string[]                 $includes  File path patterns to include in analysis
     * @param string[]                 $excludes  File path patterns to exclude from analysis
     * @param AnalyzerType             $type      Analyzer type to use
     * @param null|DebugAnalyzerConfig $debug     Debug analyzer configuration
     */
    public function __construct(
        public string $basePath,
        public string $direction,
        public ?int $level = null,
        public array $includes = [],
        public array $excludes = [],
        public AnalyzerType $type = AnalyzerType::Debug,
        public ?DebugAnalyzerConfig $debug = new DebugAnalyzerConfig(),
    ) {
        assert($this->basePath !== '');
        assert(in_array($this->direction, ['uses', 'used-by'], true));
        assert(is_null($this->level) || $this->level > 0);
    }

    /**
     * Creates a Config instance from an array of values.
     *
     * Validates the input array structure and values using Symfony Validator.
     * All fields except basePath and direction are optional and will use
     * default values if not provided.
     *
     * @param array<string, mixed> $array Configuration data as an associative array
     *
     * @return self A validated Config instance
     *
     * @throws ConfigException If validation fails for any configuration value
     */
    public static function fromArray(array $array): self
    {
        $constraint = new Assert\Collection([
            'fields' => [
                'basePath' => new Assert\Required([
                    new Assert\NotNull(),
                    new Assert\Type('string'),
                    new Assert\NotBlank(),
                ]),
                'direction' => new Assert\Required([
                    new Assert\NotNull(),
                    new Assert\Type('string'),
                    new Assert\Choice(['uses', 'used-by']),
                ]),
                'level' => new Assert\Optional([
                    new Assert\Type('int'),
                    new Assert\GreaterThan(0),
                ]),
                'includes' => new Assert\Optional([
                    new Assert\Type('array'),
                    new Assert\All([
                        new Assert\Type('string'),
                        new Assert\NotBlank(),
                    ]),
                ]),
                'excludes' => new Assert\Optional([
                    new Assert\Type('array'),
                    new Assert\All([
                        new Assert\Type('string'),
                        new Assert\NotBlank(),
                    ]),
                ]),
                'type' => new Assert\Required([
                    new Assert\NotNull(),
                    new Assert\Type('string'),
                    new Assert\Choice(
                        array_map(fn ($case) => $case->value, AnalyzerType::cases()),
                    ),
                ]),
                'debug' => new Assert\Optional([
                    new Assert\Type('array'),
                ]),
            ],
            'allowExtraFields' => true,
        ]);

        $validator = Validation::createValidator();
        $violations = $validator->validate($array, $constraint);
        if (count($violations) > 0) {
            throw new ConfigException((string) $violations->get(0)->getMessage());
        }

        /**
         * @var array{
         *     basePath: string,
         *     direction: string,
         *     level?: int|null,
         *     includes?: array<string>,
         *     excludes?: array<string>,
         *     type: string,
         *     debug?: array<string, mixed>
         * } $array
         */

        return new self(
            basePath: (string) $array['basePath'],
            direction: (string) $array['direction'],
            level: isset($array['level']) ? (int) $array['level'] : null,
            includes: (array) ($array['includes'] ?? []),
            excludes: (array) ($array['excludes'] ?? []),
            type: AnalyzerType::from($array['type']),
            debug: isset($array['debug']) ? DebugAnalyzerConfig::fromArray($array['debug']) : null,
        );
    }
}
