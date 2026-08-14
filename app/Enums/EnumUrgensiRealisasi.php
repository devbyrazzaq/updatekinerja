<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Tingkat urgensi realisasi yang dipilih unit kerja saat mengajukan, sebagai penanda
 * bagi verifikator seberapa cepat realisasi ini perlu ditinjau. Default terendah
 * ({@see self::Rendah}).
 */
enum EnumUrgensiRealisasi: string implements HasColor, HasIcon, HasLabel
{
    case Rendah = 'rendah';
    case Sedang = 'sedang';
    case Tinggi = 'tinggi';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rendah => 'Rendah',
            self::Sedang => 'Sedang',
            self::Tinggi => 'Mendesak',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Rendah => 'gray',
            self::Sedang => 'warning',
            self::Tinggi => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Rendah => 'heroicon-o-arrow-down',
            self::Sedang => 'heroicon-o-exclamation-circle',
            self::Tinggi => 'heroicon-o-fire',
        };
    }

    /**
     * Keterangan singkat tiap tingkat untuk membantu unit kerja memilih.
     */
    public function description(): string
    {
        return match ($this) {
            self::Rendah => 'Dapat ditinjau sesuai antrean biasa.',
            self::Sedang => 'Sebaiknya ditinjau lebih awal.',
            self::Tinggi => 'Butuh peninjauan segera.',
        };
    }
}
