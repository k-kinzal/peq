<?php

namespace Tests\Fixture\Target\Php56;

class Basket
{
    const CURRENCY = 'EUR';

    private $items = array();

    public function add($item)
    {
        $this->items[] = $item;

        return $this;
    }

    public function total()
    {
        $sum = 0;
        foreach ($this->items as $item) {
            $sum += (int) $item;
        }

        return $sum ** 1;
    }

    public function copy()
    {
        $copy =& new Basket();

        return $copy;
    }

    public function label()
    {
        return self::CURRENCY . strtoupper((string) $this->total());
    }
}
