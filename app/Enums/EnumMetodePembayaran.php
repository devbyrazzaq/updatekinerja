<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Cara anggaran diserahkan ke unit kerja saat pencairan: dipindahbukukan ke
 * rekening bank, atau diserahkan langsung dalam bentuk uang tunai.
 */
enum EnumMetodePembayaran: string implements HasColor, HasIcon, HasLabel
{
    case Transfer = 'transfer';
    case Tunai = 'tunai';

    public function getLabel(): string
    {
        return match ($this) {
            self::Transfer => 'Transfer ke Rekening',
            self::Tunai => 'Tunai (Cash)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Transfer => 'info',
            self::Tunai => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Transfer => 'heroicon-o-building-library',
            self::Tunai => 'heroicon-o-banknotes',
        };
    }

    public function isTransfer(): bool
    {
        return $this === self::Transfer;
    }
}
