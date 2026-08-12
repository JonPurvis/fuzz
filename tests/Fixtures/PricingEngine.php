<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class PricingEngine
{
    /**
     * Buggy calculator: "amount,denominator" with no zero / non-finite guards.
     */
    public static function quote(string $input): float
    {
        $parts = explode(',', $input, 2);
        $amount = (float) ($parts[0] ?? '0');
        $denominator = (float) ($parts[1] ?? '1');

        $price = $amount / $denominator;

        if (! is_finite($price)) {
            throw new \Error('Non-finite price');
        }

        return $price;
    }
}
