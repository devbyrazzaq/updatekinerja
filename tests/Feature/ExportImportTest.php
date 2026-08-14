<?php

namespace Tests\Feature;

use App\Exports\KategorisExport;
use App\Imports\KategorisImport;
use App\Imports\RekeningsImport;
use App\Imports\TahunKerjasImport;
use App\Models\Kategori;
use App\Models\Periode;
use App\Models\Rekening;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class ExportImportTest extends TestCase
{
    use RefreshDatabase;

    private function writeCsv(string $name, string $content): string
    {
        $path = sys_get_temp_dir().'/'.$name;
        file_put_contents($path, $content);

        return $path;
    }

    public function test_kategori_import_creates_records(): void
    {
        $path = $this->writeCsv('kategori.csv', "code,name,description,is_active\nKAT-01,Pendidikan,Kegiatan pendidikan,1\nKAT-02,Penelitian,,1\n");

        $result = (new KategorisImport)->import($path);

        $this->assertSame(2, $result->imported);
        $this->assertFalse($result->failed());
        $this->assertDatabaseHas(Kategori::class, ['code' => 'KAT-01', 'name' => 'Pendidikan', 'slug' => 'pendidikan']);
        $this->assertDatabaseHas(Kategori::class, ['code' => 'KAT-02', 'name' => 'Penelitian']);

        @unlink($path);
    }

    public function test_kategori_import_validates_all_rows_before_saving(): void
    {
        // Baris kedua tidak punya name -> seluruh berkas ditolak (all-or-nothing).
        $path = $this->writeCsv('kategori-invalid.csv', "code,name,description,is_active\nKAT-01,Pendidikan,,1\nKAT-02,,,1\n");

        $result = (new KategorisImport)->import($path);

        $this->assertTrue($result->failed());
        $this->assertDatabaseCount(Kategori::class, 0);

        @unlink($path);
    }

    public function test_tahun_kerja_import_fails_when_periode_missing(): void
    {
        $path = $this->writeCsv('tk.csv', "periode,name,tahun,description,start_datetime,end_datetime,is_active\nTidak Ada,TA 2026,2026,,2026-01-01 00:00:00,2026-12-31 00:00:00,1\n");

        $result = (new TahunKerjasImport)->import($path);

        $this->assertTrue($result->failed());
        $this->assertDatabaseCount(TahunKerja::class, 0);

        @unlink($path);
    }

    public function test_tahun_kerja_import_resolves_periode_by_name(): void
    {
        Periode::create(['name' => 'Periode 2026', 'start_datetime' => now(), 'end_datetime' => now()->addYear()]);

        $path = $this->writeCsv('tk2.csv', "periode,name,tahun,description,start_datetime,end_datetime,is_active\nPeriode 2026,TA 2026,2026,,2026-01-01 00:00:00,2026-12-31 00:00:00,1\n");

        $result = (new TahunKerjasImport)->import($path);

        $this->assertSame(1, $result->imported);
        $this->assertDatabaseHas(TahunKerja::class, ['name' => 'TA 2026', 'tahun' => 2026]);

        @unlink($path);
    }

    public function test_rekening_import_resolves_unit_kerja_by_name(): void
    {
        $unit = UnitKerja::create(['name' => 'Fakultas Teknik']);

        // Baris 1 dengan unit kerja, baris 2 tanpa unit (rekening umum).
        $path = $this->writeCsv('rekening.csv', "code,unit_kerja,name,description,is_active\n5.1.02.01,Fakultas Teknik,Belanja ATK,,1\n5.2.03.02,,Belanja Perjalanan Dinas,,1\n");

        $result = (new RekeningsImport)->import($path);

        $this->assertSame(2, $result->imported);
        $this->assertDatabaseHas(Rekening::class, ['code' => '5.1.02.01', 'unit_kerja_id' => $unit->id]);
        $this->assertDatabaseHas(Rekening::class, ['code' => '5.2.03.02', 'unit_kerja_id' => null]);

        @unlink($path);
    }

    public function test_kategori_export_returns_download_response(): void
    {
        Kategori::create(['code' => 'KAT-01', 'name' => 'Pendidikan']);

        $response = (new KategorisExport)->download();

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
    }
}
