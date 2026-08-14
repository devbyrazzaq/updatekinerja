<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EnumStatusPengajuan: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Diajukan = 'diajukan';
    case Revisi = 'revisi';
    case Ditolak = 'ditolak';
    case Diterima = 'diterima';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Diajukan => 'Diajukan',
            self::Revisi => 'Revisi',
            self::Ditolak => 'Ditolak',
            self::Diterima => 'Diterima',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Diajukan => 'info',
            self::Revisi => 'warning',
            self::Ditolak => 'danger',
            self::Diterima => 'success',
        };
    }

    /**
     * Tahapan stepper tempat status ini berada. Revisi dikembalikan ke tahap
     * Pengajuan Program karena unit kerja perlu memperbaiki pengajuannya, sedangkan
     * Ditolak tetap di tahap Verifikasi Rektor sebagai hasil akhir tahap tersebut.
     */
    public function tahapan(): EnumTahapanPengajuan
    {
        return match ($this) {
            self::Draft, self::Revisi => EnumTahapanPengajuan::Draf,
            self::Diajukan, self::Ditolak => EnumTahapanPengajuan::VerifikasiRektor,
            self::Diterima => EnumTahapanPengajuan::Diterima,
        };
    }
}
