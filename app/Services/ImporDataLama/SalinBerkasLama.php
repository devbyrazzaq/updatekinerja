<?php

namespace App\Services\ImporDataLama;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Memindahkan berkas unggahan aplikasi lama ke disk penyimpanan sistem ini.
 *
 * Aplikasi lama menaruh dokumen di direktori publik dengan struktur folder per
 * pengajuan; sistem ini menyimpannya pada disk privat dengan direktori datar per
 * jenis dokumen (`proposal-realisasi`, `laporan-realisasi`) yang diakses lewat URL
 * sementara. Kelas ini hanya menyalin berkasnya; penulisan path ke basis data
 * menjadi urusan kelas impor.
 */
class SalinBerkasLama
{
    public const DIREKTORI_PROPOSAL = 'proposal-realisasi';

    public const DIREKTORI_LAPORAN = 'laporan-realisasi';

    public const DIREKTORI_AVATAR = 'avatar';

    private readonly Filesystem $disk;

    public function __construct()
    {
        $this->disk = Storage::disk(config('filament.default_filesystem_disk'));
    }

    /**
     * Menyalin berkas dari arsip zip. Kunci peta adalah path tujuan pada disk
     * aplikasi, nilainya daftar nama entri yang dicoba berurutan — berguna karena
     * sebagian dokumen tercatat pada satu direktori tetapi terarsip di direktori
     * kembarannya.
     *
     * Berkas dialirkan satu per satu (bukan diekstrak seluruhnya lebih dulu) supaya
     * arsip ratusan megabita tidak perlu mendarat di direktori sementara.
     *
     * @param  array<string, array<int, string>>  $berkas
     * @return array{disalin: int, dilewati: int, gagal: array<int, string>}
     */
    public function dariZip(string $arsip, array $berkas): array
    {
        if (! is_file($arsip)) {
            throw new RuntimeException("Arsip berkas lama tidak ditemukan: {$arsip}");
        }

        $zip = new ZipArchive;

        if ($zip->open($arsip) !== true) {
            throw new RuntimeException("Arsip berkas lama gagal dibuka: {$arsip}");
        }

        $hasil = ['disalin' => 0, 'dilewati' => 0, 'gagal' => []];

        foreach ($berkas as $tujuan => $kandidat) {
            if ($this->disk->exists($tujuan)) {
                $hasil['dilewati']++;

                continue;
            }

            $sumber = false;

            foreach ($kandidat as $entri) {
                $sumber = $zip->getStream($entri);

                if ($sumber !== false) {
                    break;
                }
            }

            if ($sumber === false) {
                $hasil['gagal'][] = $kandidat[0] ?? $tujuan;

                continue;
            }

            $this->disk->writeStream($tujuan, $sumber);
            fclose($sumber);
            $hasil['disalin']++;
        }

        $zip->close();

        return $hasil;
    }

    /**
     * Menyalin berkas dari sebuah direktori di berkas sistem. Kunci peta adalah path
     * sumber (absolut atau relatif terhadap `$basis`), nilainya path tujuan.
     *
     * @param  array<string, string>  $berkas
     * @return array{disalin: int, dilewati: int, gagal: array<int, string>}
     */
    public function dariDirektori(string $basis, array $berkas): array
    {
        $hasil = ['disalin' => 0, 'dilewati' => 0, 'gagal' => []];

        foreach ($berkas as $relatif => $tujuan) {
            if ($this->disk->exists($tujuan)) {
                $hasil['dilewati']++;

                continue;
            }

            $sumber = rtrim($basis, '/').'/'.ltrim($relatif, '/');

            if (! is_file($sumber)) {
                $hasil['gagal'][] = $relatif;

                continue;
            }

            $aliran = fopen($sumber, 'rb');

            if ($aliran === false) {
                $hasil['gagal'][] = $relatif;

                continue;
            }

            $this->disk->writeStream($tujuan, $aliran);
            fclose($aliran);
            $hasil['disalin']++;
        }

        return $hasil;
    }

    /**
     * Path tujuan sebuah dokumen pada disk aplikasi. Nama berkas aplikasi lama sudah
     * berupa string acak panjang sehingga aman dipakai apa adanya; awalan dipakai
     * untuk data yang nama berkasnya pendek dan berpotensi kembar.
     */
    public function pathTujuan(string $direktori, string $namaBerkas, ?string $awalan = null): string
    {
        $nama = basename($namaBerkas);

        return $direktori.'/'.($awalan !== null ? $awalan.'-'.$nama : $nama);
    }

    public function disk(): Filesystem
    {
        return $this->disk;
    }
}
