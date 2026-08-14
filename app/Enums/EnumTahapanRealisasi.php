<?php

namespace App\Enums;

/**
 * Tahapan linear yang ditampilkan pada stepper realisasi program kerja. Berbeda
 * dengan {@see EnumStatusRealisasi} yang menyimpan status mentah (termasuk cabang
 * Revisi/Ditolak), enum ini hanya berisi langkah maju yang dilalui sebuah realisasi.
 */
enum EnumTahapanRealisasi: string
{
    case Draf = 'draf';
    case VerifikasiRektor = 'verifikasi_rektor';
    case VerifikasiWakil = 'verifikasi_wakil';
    case VerifikasiKeuangan = 'verifikasi_keuangan';
    case Pencairan = 'pencairan';
    case Pelaksanaan = 'pelaksanaan';
    case VerifikasiLaporan = 'verifikasi_laporan';
    case Selesai = 'selesai';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draf => 'Pengajuan Realisasi',
            self::VerifikasiRektor => 'Verifikasi Rektor',
            self::VerifikasiWakil => 'Verifikasi Wakil Rektor',
            self::VerifikasiKeuangan => 'Verifikasi Biro Keuangan',
            self::Pencairan => 'Pencairan Anggaran',
            self::Pelaksanaan => 'Pelaksanaan Kegiatan',
            self::VerifikasiLaporan => 'Verifikasi Laporan',
            self::Selesai => 'Selesai',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Draf => 'Pengisian dan pengajuan realisasi beserta dokumen proposal oleh unit kerja.',
            self::VerifikasiRektor => 'Realisasi ditinjau dan menunggu persetujuan Rektor.',
            self::VerifikasiWakil => 'Realisasi menunggu persetujuan Wakil Rektor.',
            self::VerifikasiKeuangan => 'Realisasi menunggu verifikasi dan penjadwalan pencairan oleh Biro Keuangan.',
            self::Pencairan => 'Pencairan dijadwalkan dan anggaran diserahkan kepada unit kerja.',
            self::Pelaksanaan => 'Kegiatan dilaksanakan unit kerja, menunggu laporan pelaksanaan.',
            self::VerifikasiLaporan => 'Laporan pelaksanaan ditinjau oleh verifikator laporan.',
            self::Selesai => 'Laporan disetujui dan realisasi selesai.',
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
