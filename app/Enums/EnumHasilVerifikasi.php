<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EnumHasilVerifikasi: string implements HasColor, HasLabel
{
    case Setuju = 'setuju';
    case Revisi = 'revisi';
    case Tolak = 'tolak';

    public function getLabel(): string
    {
        return match ($this) {
            self::Setuju => 'Disetujui',
            self::Revisi => 'Revisi',
            self::Tolak => 'Ditolak',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Setuju => 'success',
            self::Revisi => 'warning',
            self::Tolak => 'danger',
        };
    }
}
