<?php

namespace App\Services;

use App\Exports\ReferensiProgramKerjaExport;
use App\Imports\CapaianProgramKerjasImport;

/**
 * Kode program kerja yang dipakai berkas impor capaian: satu-satunya tempat bentuk
 * kodenya ditulis, dibaca bersama oleh {@see ReferensiProgramKerjaExport} yang
 * mencetaknya dan {@see CapaianProgramKerjasImport} yang membacanya kembali.
 *
 * Ada dua bentuk karena lembar referensinya boleh berangkat dari dua sisi:
 *
 * - Angka polos (mis. `12`) menunjuk pengajuan program kerja — bentuk lama, dan tetap
 *   dikenali supaya berkas yang sudah beredar tidak perlu disusun ulang.
 * - Berawalan `PK-` (mis. `PK-7`) menunjuk daftar program kerja (penawaran) yang
 *   ditawarkan ke unit kerja. Pengajuannya dicarikan saat impor, dan dibuatkan bila
 *   program kerja itu memang belum pernah diajukan.
 *
 * Awalan itulah yang membuat kedua id tidak mungkin tertukar — tanpanya, kode "7" bisa
 * berarti pengajuan ke-7 maupun program kerja ke-7.
 */
final class KodeReferensiProgramKerja
{
    public const AWALAN_DAFTAR = 'PK-';

    public const JENIS_DAFTAR = 'daftar_program_kerja';

    public const JENIS_PENGAJUAN = 'pengajuan';

    /**
     * Kode sebuah program kerja pada Daftar Program Kerja.
     */
    public static function daftarProgramKerja(int $penawaranId): string
    {
        return self::AWALAN_DAFTAR.$penawaranId;
    }

    /**
     * Kode sebuah pengajuan program kerja.
     */
    public static function pengajuan(int $pengajuanId): string
    {
        return (string) $pengajuanId;
    }

    /**
     * Membaca kode dari sel berkas. Excel kerap menuliskan angka kembali sebagai "12.0"
     * atau menyisipkan spasi saat disalin, jadi pembacaannya sengaja longgar: yang
     * menentukan hanya ada-tidaknya awalan dan angka di belakangnya.
     *
     * @return array{jenis: string, id: int}|null null bila selnya tidak berisi kode apa pun
     */
    public static function urai(mixed $nilai): ?array
    {
        $teks = strtoupper(trim((string) $nilai));

        if ($teks === '') {
            return null;
        }

        $jenis = preg_match('/^PK\s*-?\s*\d/', $teks) === 1
            ? self::JENIS_DAFTAR
            : self::JENIS_PENGAJUAN;

        $angka = preg_replace('/\D+/', '', $teks);

        if (blank($angka)) {
            return null;
        }

        return ['jenis' => $jenis, 'id' => (int) $angka];
    }
}
