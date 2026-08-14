<?php

namespace Database\Seeders;

use App\Enums\EnumStatusTahunKerja;
use App\Models\Bank;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\PaguAnggaran;
use App\Models\Periode;
use App\Models\Program;
use App\Models\Rekening;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Master data hasil pemindahan dari aplikasi LAMADU (db_eval_kinerja.sql): periode
 * jabatan (`periods`), tahun kerja (`working_years`), bidang (`areas`), kategori
 * (`categories`), program induk, dan pagu anggaran tiap unit (`budget_ceilings`).
 *
 * Datanya sudah diekstraksi ke `database/data/master-lamadu.json` supaya seeder ini
 * tidak bergantung pada dump SQL LAMADU yang berada di luar repositori. Bank dan
 * kode akun (rekening) belum punya padanan di data lama sehingga tetap disemai di
 * sini sebagai data awal.
 */
class MasterDataSeeder extends Seeder
{
    public const DATA_PATH = 'data/master-lamadu.json';

    public function run(): void
    {
        $master = $this->masterLamadu();

        $periode = Periode::updateOrCreate(
            ['name' => $master['periode']['name']],
            [
                'description' => $master['periode']['description'],
                'start_datetime' => $master['periode']['start_datetime'],
                'end_datetime' => $master['periode']['end_datetime'],
                'is_active' => true,
            ],
        );

        $tahunKerjas = $this->seedTahunKerja($periode, $master['tahun_kerja']);

        foreach ($master['kategoris'] as $kategori) {
            Kategori::updateOrCreate(
                ['code' => $kategori['code']],
                ['name' => $kategori['name'], 'description' => $kategori['description'], 'is_active' => true],
            );
        }

        foreach ($master['bidangs'] as $bidang) {
            Bidang::updateOrCreate(
                ['code' => $bidang['code']],
                ['name' => $bidang['name'], 'is_active' => true],
            );
        }

        $this->call(UnitKerjaSeeder::class);

        foreach ($master['programs'] as $name) {
            Program::updateOrCreate(['name' => $name], ['is_active' => true]);
        }

        foreach ([
            ['5.1.02.01', 'Belanja Alat Tulis Kantor'],
            ['5.2.03.02', 'Belanja Perjalanan Dinas'],
            ['5.2.04.01', 'Belanja Jasa Narasumber'],
        ] as [$code, $name]) {
            Rekening::updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }

        foreach ([
            ['451', 'Bank Syariah Indonesia'],
            ['014', 'Bank Central Asia'],
            ['008', 'Bank Mandiri'],
            ['009', 'Bank Negara Indonesia'],
            ['002', 'Bank Rakyat Indonesia'],
        ] as [$code, $name]) {
            Bank::updateOrCreate(['name' => $name], ['code' => $code, 'is_active' => true]);
        }

        $this->seedPaguAnggaran($tahunKerjas, $master['pagu_anggaran']);
    }

    /**
     * Tahun kerja LAMADU, urut dari yang paling lama supaya slot Berjalan berakhir
     * pada tahun kerja terbaru.
     *
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array<string, TahunKerja> Tahun kerja per nama, dipakai saat menyemai pagu.
     */
    private function seedTahunKerja(Periode $periode, array $definitions): array
    {
        $tahunKerjas = [];

        foreach ($definitions as $definition) {
            $tahunKerjas[$definition['name']] = TahunKerja::updateOrCreate(
                ['periode_id' => $periode->id, 'name' => $definition['name']],
                [
                    'tahun' => $definition['tahun'],
                    'description' => $definition['description'],
                    'start_datetime' => $definition['start_datetime'],
                    'end_datetime' => $definition['end_datetime'],
                    'status' => EnumStatusTahunKerja::from($definition['status']),
                ],
            );
        }

        return $tahunKerjas;
    }

    /**
     * Pagu anggaran per unit kerja. Unit yang tidak memiliki pagu pada data lama
     * memang dibiarkan kosong, bukan diberi nilai bawaan.
     *
     * @param  array<string, TahunKerja>  $tahunKerjas
     * @param  array<int, array<string, mixed>>  $definitions
     */
    private function seedPaguAnggaran(array $tahunKerjas, array $definitions): void
    {
        foreach ($definitions as $definition) {
            $tahunKerja = $tahunKerjas[$definition['tahun_kerja']] ?? null;
            $unitKerja = UnitKerja::where('slug', $definition['unit_kerja'])->first();

            if ($tahunKerja === null || $unitKerja === null) {
                throw new RuntimeException("Pagu anggaran LAMADU merujuk data yang tidak ada: {$definition['tahun_kerja']} / {$definition['unit_kerja']}.");
            }

            PaguAnggaran::updateOrCreate(
                ['tahun_kerja_id' => $tahunKerja->id, 'unit_kerja_id' => $unitKerja->id],
                [
                    'amount' => $definition['amount'],
                    'description' => "Pagu {$tahunKerja->name} sesuai data LAMADU.",
                ],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function masterLamadu(): array
    {
        $path = database_path(static::DATA_PATH);

        if (! is_file($path)) {
            throw new RuntimeException("Berkas master data LAMADU tidak ditemukan: {$path}");
        }

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }
}
