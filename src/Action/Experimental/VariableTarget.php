<?php

declare(strict_types=1);

namespace App\Action\Experimental;

/**
 * Normalizes the experimental variable address independently of CLI configuration.
 */
final readonly class VariableTarget
{
    /**
     * Stores the analyzer symbol and optional local variable filter.
     */
    public function __construct(public string $symbol, public ?string $variable) {}

    /**
     * Accepts Class:method$local, Class:$property, function$local and legacy callables.
     *
     * @throws InspectionRejected If two variable selectors disagree or the address is malformed
     */
    public static function parse(string $target, ?string $variable): self
    {
        $parts = explode('$', $target);
        if (count($parts) > 2 || (isset($parts[1]) && preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/D', $parts[1]) !== 1)) {
            throw new InspectionRejected('Invalid variable target. Use Class:method$variable or Class:$property.');
        }
        $embedded = isset($parts[1]) ? '$'.$parts[1] : null;
        if ($embedded !== null && $variable !== null && $embedded !== '$'.ltrim($variable, '$')) {
            throw new InspectionRejected('The target variable conflicts with --variable.');
        }
        $symbol = preg_replace('/(?<!:):(?!:)/', '::', $parts[0]);
        assert($symbol !== null);
        if ($embedded !== null && str_ends_with($symbol, '::')) {
            $symbol .= $embedded;
        }
        if ($symbol === '' || substr_count($symbol, '::') > 1) {
            throw new InspectionRejected('A variable target must name a class member or function.');
        }

        return new self($symbol, $embedded ?? $variable);
    }
}
