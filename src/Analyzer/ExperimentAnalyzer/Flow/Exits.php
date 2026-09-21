<?php

declare(strict_types=1);

namespace App\Analyzer\ExperimentAnalyzer\Flow;

/**
 * Normal and abrupt exits must not flow into the same following statement.
 */
final class Exits
{
    /**
     * @var array<int, list<State>>
     */
    public array $breaks = [];

    /**
     * @var array<int, list<State>>
     */
    public array $continues = [];

    /**
     * Creates this value with its explicit analysis inputs.
     */
    public function __construct(public ?State $normal) {}

    /**
     * Retains abrupt exits while normal flow is handled separately.
     */
    public function absorb(self $other, bool $consumeLoop = false): void
    {
        $this->breaks = self::combine($this->breaks, $other->breaks, $consumeLoop);
        $this->continues = self::combine($this->continues, $other->continues, $consumeLoop);
    }

    /**
     * Carries abrupt exits outward, consuming one loop or switch level when asked.
     *
     * @param array<int, list<State>> $existing
     * @param array<int, list<State>> $incoming
     *
     * @return array<int, list<State>>
     */
    public static function combine(array $existing, array $incoming, bool $consumeLoop): array
    {
        foreach ($incoming as $depth => $states) {
            $destination = $consumeLoop ? $depth - 1 : $depth;
            if ($destination > 0) {
                $existing[$destination] = array_merge($existing[$destination] ?? [], $states);
            }
        }

        return $existing;
    }
}
