<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Siklus hidup sebuah tahun kerja. Sistem menyediakan dua slot yang hidup
 * berdampingan — satu tahun Berjalan dan satu tahun Perencanaan — sehingga pagu,
 * penawaran, dan pengajuan tahun mendatang bisa disusun tanpa menyembunyikan
 * pelaksanaan tahun yang sedang berjalan.
 *
 * Enum ini menjadi satu-satunya sumber kebenaran aturan fase: modul mana yang
 * terbuka untuk tahun berstatus apa.
 */
enum EnumStatusTahunKerja: string implements HasColor, HasDescription, HasLabel
{
    case Perencanaan = 'perencanaan';
    case Berjalan = 'berjalan';
    case Penutupan = 'penutupan';
    case Selesai = 'selesai';

    public function getLabel(): string
    {
        return match ($this) {
            self::Perencanaan => 'Perencanaan',
            self::Berjalan => 'Berjalan',
            self::Penutupan => 'Penutupan',
            self::Selesai => 'Selesai',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Perencanaan => 'Pagu, penawaran, pengajuan, dan verifikasi pengajuan tahun mendatang sudah bisa disusun. Seluruh rantai realisasi masih terkunci.',
            self::Berjalan => 'Tahun kerja yang sedang dijalankan: perencanaan maupun pelaksanaan sama-sama terbuka.',
            self::Penutupan => 'Pengajuan dan realisasi baru ditutup, tetapi realisasi yang sudah berjalan masih boleh dituntaskan sampai laporan dan penyelesaian anggarannya beres.',
            self::Selesai => 'Tahun kerja tidak menempati slot mana pun — entah sudah tuntas atau belum pernah dijalankan. Selama berstatus ini datanya hanya bisa dibaca.',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Perencanaan => 'info',
            self::Berjalan => 'success',
            self::Penutupan => 'warning',
            self::Selesai => 'gray',
        };
    }

    /**
     * Boleh menyusun pagu anggaran, penawaran, pengajuan, dan verifikasi pengajuan.
     */
    public function bolehPerencanaan(): bool
    {
        return match ($this) {
            self::Perencanaan, self::Berjalan => true,
            self::Penutupan, self::Selesai => false,
        };
    }

    /**
     * Boleh menjalankan realisasi, pencairan, pelaporan, dan verifikasinya. Tahun
     * Penutupan tetap termasuk agar realisasi yang tertinggal bisa dituntaskan.
     */
    public function bolehPelaksanaan(): bool
    {
        return match ($this) {
            self::Berjalan, self::Penutupan => true,
            self::Perencanaan, self::Selesai => false,
        };
    }

    /**
     * Boleh membuat dan mengajukan realisasi baru. Hanya tahun yang benar-benar
     * berjalan; tahun Penutupan sebatas menyelesaikan realisasi yang sudah ada.
     */
    public function bolehRealisasiBaru(): bool
    {
        return $this === self::Berjalan;
    }

    public function terkunci(): bool
    {
        return $this === self::Selesai;
    }

    /**
     * Status yang menempati salah satu dari dua slot konteks, sehingga hanya boleh
     * dipegang satu tahun kerja pada satu waktu.
     *
     * @return array<int, self>
     */
    public static function slotTunggal(): array
    {
        return [self::Berjalan, self::Perencanaan];
    }

    public function adalahSlotTunggal(): bool
    {
        return in_array($this, self::slotTunggal(), true);
    }
}
