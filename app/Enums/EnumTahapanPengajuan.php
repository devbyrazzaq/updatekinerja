<?php

namespace App\Enums;

/**
 * Tahapan linear yang ditampilkan pada stepper pengajuan program kerja. Berbeda
 * dengan {@see EnumStatusPengajuan} yang menyimpan status mentah (termasuk cabang
 * Revisi/Ditolak), enum ini hanya berisi langkah maju yang dilalui sebuah pengajuan.
 */
enum EnumTahapanPengajuan: string
{
    case Draf = 'draf';
    case VerifikasiRektor = 'verifikasi_rektor';
    case Diterima = 'diterima';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draf => 'Pengajuan Program',
            self::VerifikasiRektor => 'Verifikasi Rektor',
            self::Diterima => 'Diterima',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Draf => 'Pengisian dan pengajuan form program kerja beserta alokasi anggaran oleh unit kerja.',
            self::VerifikasiRektor => 'Pengajuan ditinjau dan menunggu verifikasi serta persetujuan dari Rektor.',
            self::Diterima => 'Pengajuan disetujui dan program kerja siap untuk direalisasikan.',
        };
    }

    /**
     * Seluruh tahapan sesuai urutan alur, dipakai untuk merender stepper.
     *
     * @return array<int, self>
     */
    public static function flowCases(): array
    {
        return self::cases();
    }

    /**
     * Posisi 1-based tahapan ini dalam alur.
     */
    public function order(): int
    {
        return array_search($this, self::flowCases(), true) + 1;
    }

    public function isBefore(self $other): bool
    {
        return $this->order() < $other->order();
    }

    public function isAfter(self $other): bool
    {
        return $this->order() > $other->order();
    }
}
