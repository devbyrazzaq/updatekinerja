<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Lama berlangsungnya kegiatan yang menghasilkan pemasukan: satu hari saja, atau
 * membentang pada sebuah rentang tanggal (mulai sampai selesai).
 */
enum EnumJenisWaktuPemasukan: string implements HasLabel
{
    case SatuHari = 'satu_hari';
    case Rentang = 'rentang';

    public function getLabel(): string
    {
        return match ($this) {
            self::SatuHari => '1 Hari',
            self::Rentang => 'Rentang Waktu',
        };
    }

    public function isRentang(): bool
    {
        return $this === self::Rentang;
    }
}
