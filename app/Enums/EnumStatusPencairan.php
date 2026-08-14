<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EnumStatusPencairan: string implements HasColor, HasLabel
{
    case Dijadwalkan = 'dijadwalkan';
    case MenungguLaporan = 'menunggu_laporan';
    case Dicairkan = 'dicairkan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Dijadwalkan => 'Dijadwalkan',
            self::MenungguLaporan => 'Menunggu Laporan',
            self::Dicairkan => 'Sudah Dicairkan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Dijadwalkan => 'info',
            self::MenungguLaporan => 'warning',
            self::Dicairkan => 'success',
        };
    }
}
