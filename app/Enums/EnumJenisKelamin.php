<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Jenis kelamin pengguna. Nilai disimpan singkat (L/P) mengikuti standar data
 * kepegawaian, label lengkap hanya dipakai untuk tampilan.
 */
enum EnumJenisKelamin: string implements HasColor, HasLabel
{
    case LakiLaki = 'L';
    case Perempuan = 'P';

    public function getLabel(): string
    {
        return match ($this) {
            self::LakiLaki => 'Laki-laki',
            self::Perempuan => 'Perempuan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::LakiLaki => 'info',
            self::Perempuan => 'danger',
        };
    }
}
