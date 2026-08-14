<?php

namespace App\Filament\Forms\StateCasts;

use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

class MoneyStateCast implements StateCast
{
    /**
     * Converts the masked rupiah value from the browser ("1.500.000") into a number.
     */
    public function get(mixed $state): ?float
    {
        $state = static::unformat($state);

        return is_numeric($state) ? (float) $state : null;
    }

    /**
     * Converts the stored number ("1500000.00") into the masked rupiah value ("1.500.000").
     */
    public function set(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        if (! is_numeric($state)) {
            return is_string($state) ? $state : null;
        }

        return number_format((float) $state, 0, ',', '.');
    }

    /**
     * Strips the thousands separators from a masked value. Values that are not masked
     * (already numeric, or invalid input) are returned untouched so validation can reject them.
     */
    public static function unformat(mixed $state): mixed
    {
        if (blank($state) || is_numeric($state) || ! is_string($state)) {
            return $state;
        }

        if (str_contains($state, ',')) {
            return str_replace(',', '.', str_replace('.', '', $state));
        }

        if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $state) === 1) {
            return str_replace('.', '', $state);
        }

        return $state;
    }
}
