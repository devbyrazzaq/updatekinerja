<?php

namespace App\Enums;

/**
 * Tahapan linear yang ditampilkan pada stepper pemasukan unit. Berbeda dengan
 * {@see EnumStatusPemasukan} yang menyimpan status mentah (termasuk cabang
 * Revisi/Ditolak), enum ini hanya berisi langkah maju yang dilalui sebuah pemasukan.
 */
enum EnumTahapanPemasukan: string
{
    case Draf = 'draf';
    case VerifikasiWakil = 'verifikasi_wakil';
    case VerifikasiKeuangan = 'verifikasi_keuangan';
    case BuktiTerima = 'bukti_terima';
    case Valid = 'valid';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draf => 'Pencatatan Pemasukan',
            self::VerifikasiWakil => 'Verifikasi Wakil Rektor',
            self::VerifikasiKeuangan => 'Verifikasi Biro Keuangan',
            self::BuktiTerima => 'Bukti Tanda Terima',
            self::Valid => 'Valid',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Draf => 'Pencatatan dan pengajuan pemasukan oleh unit kerja.',
            self::VerifikasiWakil => 'Pemasukan menunggu persetujuan Wakil Rektor.',
            self::VerifikasiKeuangan => 'Pemasukan menunggu verifikasi Biro Keuangan.',
            self::BuktiTerima => 'Unit kerja mengunggah bukti tanda terima pemasukan.',
            self::Valid => 'Pemasukan sah dan tercatat pada buku anggaran.',
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
