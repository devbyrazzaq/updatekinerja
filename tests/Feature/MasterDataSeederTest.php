<?php

namespace Tests\Feature;

use App\Enums\EnumStatusTahunKerja;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\PaguAnggaran;
use App\Models\Periode;
use App\Models\Program;
use App\Models\TahunKerja;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_master_data_from_lamadu(): void
    {
        $this->seed([SettingSeeder::class, MasterDataSeeder::class]);

        // Periode jabatan LAMADU beserta kedua tahun kerjanya; hanya tahun terbaru
        // yang menempati slot Berjalan.
        $periode = Periode::where('is_active', true)->first();
        $this->assertNotNull($periode);
        $this->assertSame(2, TahunKerja::where('periode_id', $periode->id)->count());

        $berjalan = TahunKerja::berjalan();
        $this->assertNotNull($berjalan);
        $this->assertSame(2025, $berjalan->tahun);
        $this->assertSame(
            EnumStatusTahunKerja::Selesai,
            TahunKerja::where('tahun', 2024)->value('status'),
        );

        // Bidang berasal dari tabel `areas`, kategori dari `categories` (IKU/IKT).
        $this->assertSame(['IKT', 'IKU'], Kategori::pluck('name')->sort()->values()->all());
        $this->assertGreaterThanOrEqual(11, Bidang::count());
        $this->assertGreaterThanOrEqual(10, Program::count());

        // Pagu hanya untuk unit yang memang punya pagu di data lama, jadi tidak semua
        // unit kebagian dan nominalnya bukan angka bawaan yang seragam.
        $pagu = PaguAnggaran::all();
        $this->assertGreaterThan(0, $pagu->count());
        $this->assertGreaterThan(1, $pagu->pluck('amount')->unique()->count());
        $this->assertTrue($pagu->every(fn (PaguAnggaran $p): bool => $p->tahunKerja !== null && $p->unitKerja !== null));
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed([SettingSeeder::class, MasterDataSeeder::class]);

        $jumlah = [Periode::count(), TahunKerja::count(), Bidang::count(), Kategori::count(), Program::count(), PaguAnggaran::count()];

        $this->seed(MasterDataSeeder::class);

        $this->assertSame($jumlah, [Periode::count(), TahunKerja::count(), Bidang::count(), Kategori::count(), Program::count(), PaguAnggaran::count()]);
    }
}
