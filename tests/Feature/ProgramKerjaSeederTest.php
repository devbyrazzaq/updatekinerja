<?php

namespace Tests\Feature;

use App\Models\AcuanProgramKerja;
use App\Models\KelompokAcuan;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\TahunKerja;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\ProgramKerjaSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramKerjaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_acuan_across_units_and_syncs_penawaran(): void
    {
        $this->seed([
            SettingSeeder::class,
            MasterDataSeeder::class,
            ProgramKerjaSeeder::class,
        ]);

        $kelompokAktif = KelompokAcuan::active();
        $tahunKerja = TahunKerja::berjalan();

        $this->assertNotNull($kelompokAktif);
        $this->assertNotNull($tahunKerja);

        // Semua acuan tersimpan pada kelompok acuan aktif dan tersebar di banyak unit.
        $acuan = AcuanProgramKerja::all();
        $this->assertGreaterThanOrEqual(8, $acuan->count());
        $this->assertTrue($acuan->every(fn (AcuanProgramKerja $a): bool => $a->kelompok_acuan_id === $kelompokAktif->id));
        $this->assertGreaterThanOrEqual(4, $acuan->pluck('unit_kerja_id')->unique()->count());

        // Sinkronisasi membentuk satu penawaran untuk tiap acuan pada tahun kerja aktif.
        $this->assertSame(
            $acuan->count(),
            PenawaranProgramKerja::where('tahun_kerja_id', $tahunKerja->id)->count(),
        );

        // Setiap acuan memiliki target untuk seluruh tahun kelompok acuan.
        $jumlahTahun = count($kelompokAktif->tahunList());
        $acuan->each(fn (AcuanProgramKerja $a) => $this->assertSame($jumlahTahun, $a->targets()->count()));

        // Tahun kerja berjalan memakai kelompok acuan tersebut, dan seeder berhenti
        // sampai penawaran: pengajuan diisi dari aplikasi, bukan disemai.
        $this->assertSame($kelompokAktif->id, $tahunKerja->refresh()->kelompok_acuan_id);
        $this->assertSame(0, PengajuanProgramKerja::count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed([SettingSeeder::class, MasterDataSeeder::class, ProgramKerjaSeeder::class]);
        $acuanCount = AcuanProgramKerja::count();
        $penawaranCount = PenawaranProgramKerja::count();

        // Menjalankan ulang tidak menggandakan data.
        $this->seed(ProgramKerjaSeeder::class);

        $this->assertSame($acuanCount, AcuanProgramKerja::count());
        $this->assertSame($penawaranCount, PenawaranProgramKerja::count());
    }
}
