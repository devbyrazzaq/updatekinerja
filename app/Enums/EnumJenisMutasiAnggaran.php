<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Jenis peristiwa yang menggerakkan anggaran unit kerja pada Buku Anggaran.
 *
 * Buku ini berbasis kas: anggaran baru dianggap keluar ketika benar-benar dicairkan,
 * bukan ketika pengajuan dibuat, sehingga pengajuan yang masih draf maupun yang masih
 * diverifikasi belum muncul di buku.
 *
 * Pemasukan berdiri di kolomnya sendiri: tampil sebagai penerimaan unit kerja tetapi
 * tidak menggerakkan saldo anggaran, karena belum menambah plafon yang dipakai
 * memvalidasi pengajuan.
 */
enum EnumJenisMutasiAnggaran: string implements HasColor, HasIcon, HasLabel
{
    case PaguDitetapkan = 'pagu_ditetapkan';
    case AnggaranDicairkan = 'anggaran_dicairkan';
    case SisaDikembalikan = 'sisa_dikembalikan';
    case KekuranganDilunasi = 'kekurangan_dilunasi';
    case Pemasukan = 'pemasukan';

    public function getLabel(): string
    {
        return match ($this) {
            self::PaguDitetapkan => 'Pagu Anggaran',
            self::AnggaranDicairkan => 'Anggaran Dicairkan',
            self::SisaDikembalikan => 'Sisa Dikembalikan',
            self::KekuranganDilunasi => 'Kekurangan Dilunasi',
            self::Pemasukan => 'Pemasukan',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::PaguDitetapkan => 'Pagu anggaran unit kerja pada tahun kerja ini.',
            self::AnggaranDicairkan => 'Anggaran diserahkan ke unit kerja untuk melaksanakan realisasi.',
            self::SisaDikembalikan => 'Sisa anggaran realisasi yang dikembalikan, menambah anggaran yang bisa dipakai.',
            self::KekuranganDilunasi => 'Kekurangan anggaran realisasi yang dituntaskan, mengurangi anggaran yang bisa dipakai.',
            self::Pemasukan => 'Penerimaan unit kerja; tercatat tetapi belum menambah plafon anggaran.',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PaguDitetapkan => 'primary',
            self::AnggaranDicairkan => 'warning',
            self::SisaDikembalikan => 'success',
            self::KekuranganDilunasi => 'danger',
            self::Pemasukan => 'info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PaguDitetapkan => 'heroicon-o-banknotes',
            self::AnggaranDicairkan => 'heroicon-o-arrow-up-right',
            self::SisaDikembalikan => 'heroicon-o-arrow-uturn-left',
            self::KekuranganDilunasi => 'heroicon-o-arrow-down-right',
            self::Pemasukan => 'heroicon-o-arrow-down-tray',
        };
    }

    /**
     * Menambah anggaran yang bisa dipakai unit kerja (sisi kredit buku).
     */
    public function adalahKredit(): bool
    {
        return match ($this) {
            self::AnggaranDicairkan, self::KekuranganDilunasi => false,
            default => true,
        };
    }

    /**
     * Berdiri di kolom pemasukan: tercatat di buku tetapi tidak menggerakkan saldo.
     */
    public function adalahPemasukan(): bool
    {
        return $this === self::Pemasukan;
    }
}
