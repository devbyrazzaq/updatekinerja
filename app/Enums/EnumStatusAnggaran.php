<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum EnumStatusAnggaran: string implements HasColor, HasDescription, HasLabel
{
    case Sisa = 'sisa';
    case Habis = 'habis';
    case Kurang = 'kurang';

    /**
     * Status anggaran laporan, dibandingkan dengan anggaran yang diterima unit kerja.
     */
    public static function fromPerbandingan(float $diterima, float $digunakan): self
    {
        return match (true) {
            $digunakan > $diterima => self::Kurang,
            $digunakan < $diterima => self::Sisa,
            default => self::Habis,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Habis => 'Anggaran Tergunakan Semua',
            self::Sisa => 'Anggaran Bersisa',
            self::Kurang => 'Anggaran Kurang',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Habis => 'Seluruh anggaran yang diterima terpakai, tanpa sisa maupun kekurangan.',
            self::Sisa => 'Ada anggaran yang tidak terpakai dan perlu dikembalikan ke Biro Keuangan.',
            self::Kurang => 'Biaya kegiatan melebihi anggaran yang diterima sehingga ada kekurangan yang perlu dilunasi.',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Sisa => 'info',
            self::Habis => 'success',
            self::Kurang => 'danger',
        };
    }

    /**
     * Status yang menyisakan selisih nominal, sehingga unit kerja perlu mengisi
     * besaran sisa/kekurangan beserta status penyelesaiannya.
     */
    public function memerlukanSelisih(): bool
    {
        return $this !== self::Habis;
    }

    /**
     * Judul isian nominal selisih sesuai arah selisihnya.
     */
    public function labelSelisih(): string
    {
        return match ($this) {
            self::Sisa => 'Nominal Sisa Anggaran',
            self::Kurang => 'Nominal Kekurangan Anggaran',
            self::Habis => 'Selisih Anggaran',
        };
    }
}
