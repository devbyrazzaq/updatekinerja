<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Asal daftar pada lembar "Referensi Program Kerja" di berkas template impor capaian.
 *
 * Keduanya menampilkan program kerja pada tahun kerja dan unit kerja yang sama —
 * bedanya titik berangkatnya. {@see self::Pengajuan} berangkat dari pengajuan yang sudah
 * diterima, jadi seluruh barisnya siap dipakai mengimpor capaian. {@see
 * self::DaftarProgramKerja} berangkat dari katalog program kerja yang ditawarkan ke unit
 * kerja, sehingga program kerja yang belum pernah diajukan pun ikut terdaftar — berguna
 * untuk memeriksa apa saja yang belum bergerak, meski barisnya belum bisa diimpor
 * sebelum program kerjanya diajukan dan diterima.
 */
enum EnumSumberReferensiProgramKerja: string implements HasLabel
{
    case Pengajuan = 'pengajuan';
    case DaftarProgramKerja = 'daftar_program_kerja';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pengajuan => 'Pengajuan Program Kerja',
            self::DaftarProgramKerja => 'Daftar Program Kerja',
        };
    }

    /**
     * Keterangan singkat pilihan, dipakai sebagai helper text maupun subjudul lembar.
     */
    public function keterangan(): string
    {
        return match ($this) {
            self::Pengajuan => 'Hanya program kerja yang pengajuannya sudah diterima — seluruh barisnya siap dipakai mengimpor capaian.',
            self::DaftarProgramKerja => 'Seluruh program kerja yang ditawarkan ke unit kerja, termasuk yang belum diajukan. Baris yang belum diajukan tidak punya kode dan belum dapat diimpor.',
        };
    }

    public static function bawaan(): self
    {
        return self::Pengajuan;
    }

    /**
     * Mengurai nilai dari konteks form; nilai asing dikembalikan ke bawaannya.
     */
    public static function dariNilai(mixed $nilai): self
    {
        if ($nilai instanceof self) {
            return $nilai;
        }

        return self::tryFrom((string) $nilai) ?? self::bawaan();
    }
}
