<?php

namespace Tests\Feature;

use App\Filament\Resources\KelompokAcuans\Pages\EditKelompokAcuan;
use App\Filament\Resources\KelompokAcuans\RelationManagers\AcuanProgramKerjasRelationManager;
use App\Imports\AcuanProgramKerjasImport;
use App\Models\AcuanProgramKerja;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\KelompokAcuan;
use App\Models\Program;
use App\Models\UnitKerja;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class KelompokAcuanImportTest extends TestCase
{
    use RefreshDatabase;

    private KelompokAcuan $kelompok;

    private UnitKerja $unit;

    private Bidang $bidang;

    private Kategori $kategori;

    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kelompok = KelompokAcuan::create(['name' => 'Program Kerja 2025 - 2029', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'is_active' => true]);
        $this->unit = UnitKerja::create(['name' => 'Fakultas Teknik']);
        $this->bidang = Bidang::create(['code' => 'BID-01', 'name' => 'Akademik']);
        $this->kategori = Kategori::create(['code' => 'KAT-01', 'name' => 'Pendidikan']);
        $this->program = Program::create(['name' => 'Tridharma Perguruan Tinggi']);
    }

    private function writeCsv(string $content): string
    {
        $path = sys_get_temp_dir().'/acuan-import-'.uniqid().'.csv';
        file_put_contents($path, $content);

        return $path;
    }

    public function test_import_attaches_acuan_to_context_kelompok(): void
    {
        $lain = KelompokAcuan::create(['name' => 'Lama 2020 - 2024', 'tahun_mulai' => 2020, 'tahun_selesai' => 2024, 'is_active' => false]);

        $path = $this->writeCsv("name,unit_kerja,bidang,kategori,program\nAkuan Impor,Fakultas Teknik,Akademik,Pendidikan,Tridharma Perguruan Tinggi\n");

        // Konteks menunjuk kelompok non-aktif untuk memastikan konteks yang menang.
        $result = (new AcuanProgramKerjasImport)
            ->withContext(['kelompok_acuan_id' => $lain->id])
            ->import($path);

        @unlink($path);

        $this->assertFalse($result->failed());
        $this->assertDatabaseHas(AcuanProgramKerja::class, [
            'name' => 'Akuan Impor',
            'kelompok_acuan_id' => $lain->id,
        ]);
    }

    public function test_import_falls_back_to_active_kelompok_without_context(): void
    {
        $path = $this->writeCsv("name,unit_kerja,bidang,kategori,program\nAkuan Tanpa Konteks,Fakultas Teknik,Akademik,Pendidikan,Tridharma Perguruan Tinggi\n");

        $result = (new AcuanProgramKerjasImport)->import($path);

        @unlink($path);

        $this->assertFalse($result->failed());
        $this->assertDatabaseHas(AcuanProgramKerja::class, [
            'name' => 'Akuan Tanpa Konteks',
            'kelompok_acuan_id' => $this->kelompok->id,
        ]);
    }

    public function test_relation_manager_creates_acuan_under_owner_kelompok(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('bypass_data_scope', 'web'));
        $this->actingAs($user);

        Livewire::test(AcuanProgramKerjasRelationManager::class, [
            'ownerRecord' => $this->kelompok,
            'pageClass' => EditKelompokAcuan::class,
        ])
            ->callAction(TestAction::make('create')->table(), [
                'name' => 'Acuan Manual',
                'unit_kerja_id' => $this->unit->id,
                'bidang_id' => $this->bidang->id,
                'kategori_id' => $this->kategori->id,
                'program_id' => $this->program->id,
                'targets' => [],
            ])
            ->assertHasNoErrors();

        $this->assertDatabaseHas(AcuanProgramKerja::class, [
            'name' => 'Acuan Manual',
            'kelompok_acuan_id' => $this->kelompok->id,
            'unit_kerja_id' => $this->unit->id,
        ]);
    }
}
