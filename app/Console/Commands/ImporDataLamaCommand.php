<?php

namespace App\Console\Commands;

use App\Enums\EnumStatusTahunKerja;
use App\Models\TahunKerja;
use App\Services\ImporDataLama\ImporLama2023;
use App\Services\ImporDataLama\ImporLama2025;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Memindahkan data tahun kerja sebelumnya dari dua aplikasi pendahulu ke sistem ini:
 * LAMADU (basis data `kinerja_2025`, memuat tahun kerja 2024 dan 2025) dan aplikasi
 * kinerja generasi pertama (`kinerja_2023`).
 *
 * Perintah ini aman diulang: tiap baris dikenali lewat kunci alaminya sehingga data
 * yang sudah pindah tidak terduplikasi.
 */
#[Signature('impor:data-lama
    {--sumber=semua : Sumber data yang diimpor: semua, 2025, atau 2023}
    {--tanpa-berkas : Lewati penyalinan dokumen, hanya pindahkan basis datanya}
    {--arsip-2025= : Path arsip berkas LAMADU (bawaan: db/public-2025.zip)}
    {--berkas-2023= : Path direktori dokumen aplikasi 2023 (bawaan: db/kinerja2023)}')]
#[Description('Impor pengajuan, realisasi, dan dokumen tahun kerja dari aplikasi lama')]
class ImporDataLamaCommand extends Command
{
    public function handle(ImporLama2025 $impor2025, ImporLama2023 $impor2023): int
    {
        $sumber = (string) $this->option('sumber');
        $salinBerkas = ! $this->option('tanpa-berkas');

        if (! in_array($sumber, ['semua', '2025', '2023'], true)) {
            $this->components->error('Pilihan --sumber hanya boleh: semua, 2025, atau 2023.');

            return self::FAILURE;
        }

        try {
            if ($sumber === 'semua' || $sumber === '2025') {
                $this->components->info('Mengimpor data LAMADU (tahun kerja 2024 & 2025)...');
                $this->tampilkanRingkasan($impor2025->jalankan(
                    $salinBerkas,
                    $this->option('arsip-2025'),
                    fn (string $pesan) => $this->components->twoColumnDetail($pesan),
                ));
            }

            if ($sumber === 'semua' || $sumber === '2023') {
                $this->components->info('Mengimpor data aplikasi kinerja 2023...');
                $this->tampilkanRingkasan($impor2023->jalankan(
                    $salinBerkas,
                    $this->option('berkas-2023'),
                    fn (string $pesan) => $this->components->twoColumnDetail($pesan),
                ));
            }
        } catch (Throwable $kesalahan) {
            $this->components->error($kesalahan->getMessage());

            return self::FAILURE;
        }

        $this->tampilkanTahunKerja();

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $ringkasan
     */
    private function tampilkanRingkasan(array $ringkasan): void
    {
        foreach ($ringkasan as $label => $jumlah) {
            $this->components->twoColumnDetail(str_replace('_', ' ', $label), (string) $jumlah);
        }
    }

    private function tampilkanTahunKerja(): void
    {
        $this->components->info('Status tahun kerja saat ini:');

        TahunKerja::query()->orderBy('tahun')->get()->each(function (TahunKerja $tahunKerja): void {
            $status = $tahunKerja->status instanceof EnumStatusTahunKerja
                ? $tahunKerja->status->getLabel()
                : '-';

            $this->components->twoColumnDetail($tahunKerja->name, $status);
        });
    }
}
