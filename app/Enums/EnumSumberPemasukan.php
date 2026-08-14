<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EnumSumberPemasukan: string implements HasColor, HasLabel
{
    case Pengajuan = 'pengajuan';
    case Realisasi = 'realisasi';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pengajuan => 'Pengajuan Program Kerja',
            self::Realisasi => 'Realisasi Program Kerja',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pengajuan => 'info',
            self::Realisasi => 'success',
        };
    }
}
