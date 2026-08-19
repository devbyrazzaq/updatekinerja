<?php

namespace App\Services;

use App\Enums\EnumJenisDokumenRealisasi;
use App\Models\RealisasiProgramKerja;
use App\Providers\Filament\AppPanelProvider;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;

/**
 * Kode tautan dokumen realisasi: penanda pendek yang menunjuk satu realisasi beserta
 * jenis dokumennya, dipasang di dalam berkas ekspor sebagai alamat yang bisa diklik.
 *
 * Berkas proposal dan laporan disimpan pada disk privat dan hanya bisa dibuka lewat
 * `Storage::temporaryUrl()` yang umurnya pendek — alamat semacam itu mustahil ditanam
 * di dalam berkas .xlsx yang dibaca berhari-hari kemudian. Karena itu yang ditulis ke
 * berkas hanyalah kode ini; alamatnya ditukar dengan URL sementara yang baru setiap
 * kali tautannya diikuti, di halaman pratinjau yang menuntut pengguna sudah masuk.
 *
 * Kodenya sendiri bukan kunci akses: pembatasan data tetap dijaga halaman tujuan.
 * Tanda tangannya ada supaya kode tidak bisa diarang-arang menjadi kode realisasi
 * lain, dan supaya tautan yang rusak ketahuan sejak awal.
 */
final class KodeDokumenRealisasi
{
    /**
     * Nama rute penukar kode (tanpa awalan panel), didaftarkan pada
     * {@see AppPanelProvider} sebagai rute terautentikasi.
     */
    public const NAMA_RUTE = 'dokumen-realisasi';

    private const PEMISAH = '.';

    /** Panjang tanda tangan yang disertakan; cukup panjang untuk menutup tebakan. */
    private const PANJANG_TANDA = 12;

    public static function untuk(RealisasiProgramKerja $realisasi, EnumJenisDokumenRealisasi $jenis): string
    {
        $muatan = self::sandikan($realisasi->uuid.'|'.$jenis->value);

        return $muatan.self::PEMISAH.self::tanda($muatan);
    }

    /**
     * Alamat lengkap penukar kode, siap ditanam pada sel berkas ekspor.
     */
    public static function tautan(RealisasiProgramKerja $realisasi, EnumJenisDokumenRealisasi $jenis): string
    {
        return Filament::getDefaultPanel()->route(self::NAMA_RUTE, [
            'kode' => self::untuk($realisasi, $jenis),
        ]);
    }

    /**
     * Bongkar kode menjadi tujuannya; null bila kodenya cacat, dipotong, atau
     * tanda tangannya tidak cocok.
     *
     * @return array{uuid: string, jenis: EnumJenisDokumenRealisasi}|null
     */
    public static function bongkar(string $kode): ?array
    {
        [$muatan, $tanda] = array_pad(explode(self::PEMISAH, $kode, 2), 2, null);

        if (blank($muatan) || blank($tanda) || ! hash_equals(self::tanda($muatan), $tanda)) {
            return null;
        }

        [$uuid, $jenis] = array_pad(explode('|', self::bacaSandi($muatan), 2), 2, null);

        if (blank($uuid) || blank($jenis) || ($jenisDokumen = EnumJenisDokumenRealisasi::tryFrom($jenis)) === null) {
            return null;
        }

        return ['uuid' => $uuid, 'jenis' => $jenisDokumen];
    }

    private static function tanda(string $muatan): string
    {
        return substr(
            hash_hmac('sha256', $muatan, (string) Config::get('app.key')),
            0,
            self::PANJANG_TANDA,
        );
    }

    /**
     * Base64 ramah URL: tanpa `+`, `/`, maupun `=` sehingga aman menjadi satu ruas
     * alamat dan tidak dipotong penyunting spreadsheet.
     */
    private static function sandikan(string $teks): string
    {
        return rtrim(strtr(base64_encode($teks), '+/', '-_'), '=');
    }

    private static function bacaSandi(string $sandi): string
    {
        return (string) base64_decode(strtr($sandi, '-_', '+/'), true);
    }
}
