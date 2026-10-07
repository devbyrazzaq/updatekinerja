<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Jenis sebuah realisasi program kerja, menentukan alur mana yang dilaluinya.
 *
 * {@see self::Anggaran} adalah realisasi biasa: diajukan unit kerja, diverifikasi
 * berjenjang, anggarannya dicairkan, lalu dilaporkan. {@see self::TanpaAnggaran}
 * adalah capaian yang dicatat langsung dari halaman Monitoring Program Kerja — hanya
 * memperbarui ketercapaian target, tanpa menyentuh anggaran maupun verifikasi —
 * sehingga seluruh tampilan bernuansa anggaran tidak berlaku baginya.
 */
enum EnumJenisRealisasi: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Anggaran = 'anggaran';
    case TanpaAnggaran = 'tanpa_anggaran';

    public function getLabel(): string
    {
        return match ($this) {
            self::Anggaran => 'Realisasi Beranggaran',
            self::TanpaAnggaran => 'Capaian Tanpa Anggaran',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Anggaran => 'Diajukan unit kerja, diverifikasi berjenjang, anggarannya dicairkan, lalu dilaporkan.',
            self::TanpaAnggaran => 'Dicatat langsung dari Monitoring Program Kerja; hanya memperbarui ketercapaian target tanpa menyentuh anggaran.',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Anggaran => 'primary',
            self::TanpaAnggaran => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Anggaran => 'heroicon-o-banknotes',
            self::TanpaAnggaran => 'heroicon-o-clipboard-document-check',
        };
    }

    public function tanpaAnggaran(): bool
    {
        return $this === self::TanpaAnggaran;
    }
}
