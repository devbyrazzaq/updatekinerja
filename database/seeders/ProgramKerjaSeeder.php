<?php

namespace Database\Seeders;

use App\Enums\EnumModeGenerate;
use App\Models\AcuanProgramKerja;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\KelompokAcuan;
use App\Models\Program;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Services\GeneratePenawaranFromAcuan;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Acuan Program Kerja hasil pemindahan dari aplikasi LAMADU: tabel
 * `work_program_references` beserta target tahunannya (`work_program_reference_years`),
 * sudah diekstraksi ke `database/data/program-kerja-lamadu.json` supaya seeder ini
 * tidak bergantung pada dump SQL LAMADU yang berada di luar repositori.
 *
 * Data lama tidak punya kolom nama tersendiri: kolom `activity` yang dipakai sebagai
 * Nama Program Kerja, sedangkan `program` menjadi Program Induk. Kolom Aktivitas
 * dibiarkan kosong karena tidak ada padanannya, begitu pula Kode Akun yang seluruhnya
 * kosong pada data lama.
 *
 * Tahun target diambil dari urutan baris target di LAMADU, bukan kolom `year`-nya:
 * tujuh acuan salah label tahun (ada tahun kembar dan tahun yang bolong) sementara
 * urutan barisnya sudah benar 2023 sampai 2027.
 *
 * Penawaran tahun kerja berjalan dibentuk lewat {@see GeneratePenawaranFromAcuan}
 * supaya jalurnya sama dengan yang dipakai aplikasi. Seeder berhenti sampai
 * penawaran: pengajuan dan seluruh rantai pelaksanaannya diisi dari aplikasi.
 */
class ProgramKerjaSeeder extends Seeder
{
    public const DATA_PATH = 'data/program-kerja-lamadu.json';

    public function run(): void
    {
        $definitions = $this->acuanLamadu();

        $tahunTarget = collect($definitions)
            ->flatMap(fn (array $definition): array => array_column($definition['targets'], 'tahun'));

        if ($tahunTarget->isEmpty()) {
            return;
        }

        // Rentang kelompok acuan mengikuti tahun target pada data lama (2023-2027),
        // yang panjangnya sama dengan pengaturan sistem "jumlah tahun dalam 1 periode
        // jabatan".
        $tahunMulai = $tahunTarget->min();
        $tahunSelesai = $tahunTarget->max();

        $kelompokAcuan = KelompokAcuan::updateOrCreate(
            ['name' => "Program Kerja {$tahunMulai} - {$tahunSelesai}"],
            [
                'tahun_mulai' => $tahunMulai,
                'tahun_selesai' => $tahunSelesai,
                'description' => 'Rencana strategis program kerja satu periode jabatan UMLA, dipindahkan dari LAMADU.',
                'is_active' => true,
            ],
        );

        $this->seedAcuan($kelompokAcuan, $definitions);

        $tahunKerja = TahunKerja::berjalan();

        if ($tahunKerja === null) {
            return;
        }

        $tahunKerja->update(['kelompok_acuan_id' => $kelompokAcuan->id]);

        // Bentuk penawaran tahun kerja berjalan dari seluruh acuan (mode Sinkron aman
        // dijalankan berulang: menyelaraskan yang lama dan menambah yang baru).
        app(GeneratePenawaranFromAcuan::class)->handle($kelompokAcuan, $tahunKerja, EnumModeGenerate::Sinkron);
    }

    /**
     * Acuan program kerja lintas unit beserta target tiap tahunnya.
     *
     * @param  array<int, array<string, mixed>>  $definitions
     */
    private function seedAcuan(KelompokAcuan $kelompokAcuan, array $definitions): void
    {
        $unitKerjas = UnitKerja::pluck('id', 'slug');
        $bidangs = Bidang::pluck('id', 'name');
        $kategoris = Kategori::pluck('id', 'name');
        $programs = Program::pluck('id', 'name');

        foreach ($definitions as $definition) {
            $unitKerjaId = $unitKerjas[$definition['unit_kerja']] ?? null;
            $bidangId = $bidangs[$definition['bidang']] ?? null;
            $kategoriId = $kategoris[$definition['kategori']] ?? null;
            $programId = $programs[$definition['program']] ?? null;

            if ($unitKerjaId === null || $bidangId === null || $kategoriId === null || $programId === null) {
                throw new RuntimeException("Acuan \"{$definition['name']}\" merujuk master data yang belum disemai (unit kerja, bidang, kategori, atau program).");
            }

            // Satu unit kerja bisa memiliki beberapa acuan bernama sama dengan
            // indikator berbeda, jadi indikator ikut menjadi kunci pencocokan supaya
            // seeder tetap idempoten tanpa menggabungkan acuan yang berbeda.
            $acuan = AcuanProgramKerja::updateOrCreate(
                [
                    'unit_kerja_id' => $unitKerjaId,
                    'name' => $definition['name'],
                    'indikator' => $definition['indikator'],
                ],
                [
                    'kelompok_acuan_id' => $kelompokAcuan->id,
                    'bidang_id' => $bidangId,
                    'kategori_id' => $kategoriId,
                    'program_id' => $programId,
                    'nilai_standar' => $definition['nilai_standar'],
                    'satuan_nilai_standar' => $definition['satuan_nilai_standar'],
                    'is_active' => true,
                ],
            );

            foreach ($definition['targets'] as $target) {
                $acuan->targets()->updateOrCreate(
                    ['tahun' => $target['tahun']],
                    ['nilai' => $target['nilai'], 'satuan' => $target['satuan']],
                );
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function acuanLamadu(): array
    {
        $path = database_path(static::DATA_PATH);

        if (! is_file($path)) {
            throw new RuntimeException("Berkas data program kerja LAMADU tidak ditemukan: {$path}");
        }

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }
}
