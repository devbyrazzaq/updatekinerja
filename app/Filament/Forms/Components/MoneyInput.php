<?php

namespace App\Filament\Forms\Components;

use App\Filament\Forms\StateCasts\MoneyStateCast;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;

class MoneyInput extends TextInput
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prefix('Rp')
            ->inputMode('numeric')
            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
            ->stateCast(new MoneyStateCast)
            ->rule('numeric')
            ->minValue(0);
    }

    public function mutateStateForValidation(mixed $state): mixed
    {
        return parent::mutateStateForValidation(MoneyStateCast::unformat($state));
    }

    public function mutatesStateForValidation(): bool
    {
        return true;
    }
}
