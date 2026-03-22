<?php

declare(strict_types=1);

namespace App\Config;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

/**
 * Configuration for the debug analyzer.
 */
final class DebugAnalyzerConfig
{
    public function __construct(
        public readonly int $depth = 5,
        public readonly ?int $seed = null,
    ) {
        assert($this->depth > 0);
    }

    /**
     * Creates a DebugAnalyzerConfig from an array.
     *
     * @param array<string, mixed> $array Configuration data
     *
     * @throws ConfigException If validation fails
     */
    public static function fromArray(array $array): self
    {
        $constraint = new Assert\Collection(
            fields: [
                'depth' => [
                    new Assert\Type('int'),
                    new Assert\GreaterThan(0),
                ],
                'seed' => new Assert\Optional([
                    new Assert\Type('int'),
                ]),
            ],
            allowExtraFields: true,
        );

        $validator = Validation::createValidator();
        $violations = $validator->validate($array, $constraint);
        if (count($violations) > 0) {
            throw new ConfigException((string) $violations->get(0)->getMessage());
        }

        /**
         * @var array{depth: int, seed?: int} $array
         */

        return new self(
            depth: $array['depth'],
            seed: $array['seed'] ?? null,
        );
    }
}
