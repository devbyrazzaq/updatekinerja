<?php

namespace Tests\Feature;

use App\Enums\EnumModeGenerate;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Resources\AcuanProgramKerjas\Pages\CreateAcuanProgramKerja;
use App\Filament\Resources\AcuanProgramKerjas\Pages\ListAcuanProgramKerjas;
use App\Filament\Resources\PenawaranProgramKerjas\Pages\ListPenawaranProgramKerjas;
use App\Filament\Resources\PenawaranProgramKerjas\PenawaranProgramKerjaResource;
use App\Models\AcuanProgramKerja;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\KelompokAcuan;
use App\Models\PenawaranProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\GeneratePenawaranFromAcuan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProgramKerjaSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /**
     * @return array{unit: UnitKerja, bidang: Bidang, kategori: Kategori, program: Program, tahunKerja: TahunKerja}
     */
    protected function seedMasters(): array
    {
        $periode = Periode::create([
            'name' => 'Periode 2026',
            'start_datetime' => now(),
            'end_datetime' => now()->addYear(),
        ]);

        return [
            'kelompokAcuan' => KelompokAcuan::create(['name' => 'Program Kerja 2025 - 2029', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'is_active' => true]),
            'unit' => UnitKerja::create(['name' => 'Fakultas Teknik']),
            'bidang' => Bidang::create(['code' => 'B1', 'name' => 'Pendidikan']),
            'kategori' => Kategori::create(['code' => 'K1', 'name' => 'Rutin']),
            'program' => Program::create(['name' => 'Tridharma']),
            'tahunKerja' => TahunKerja::create([
                'periode_id' => $periode->id,
                'name' => 'TA 2026',
                'start_datetime' => now(),
                'end_datetime' => now()->addYear(),
                'status' => EnumStatusTahunKerja::Berjalan,
            ]),
        ];
    }

    public function test_pages_render(): void
    {
        Livewire::test(ListAcuanProgramKerjas::class)->assertOk();
        Livewire::test(CreateAcuanProgramKerja::class)->assertOk();
        Livewire::test(ListPenawaranProgramKerjas::class)->assertOk();
    }

    public function test_can_create_acuan_with_targets(): void
    {
        $m = $this->seedMasters();

        Livewire::test(CreateAcuanProgramKerja::class)
            ->fillForm([
                'name' => 'Peningkatan Mutu',
                'kelompok_acuan_id' => $m['kelompokAcuan']->id,
                'unit_kerja_id' => $m['unit']->id,
                'bidang_id' => $m['bidang']->id,
                'kategori_id' => $m['kategori']->id,
                'program_id' => $m['program']->id,
                'is_active' => true,
                'targets' => [
                    ['tahun' => 2025, 'nilai' => '90', 'satuan' => 'persen'],
                    ['tahun' => 2026, 'nilai' => '95', 'satuan' => 'persen'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $acuan = AcuanProgramKerja::firstWhere('name', 'Peningkatan Mutu');
        $this->assertNotNull($acuan);
        $this->assertSame('peningkatan-mutu', $acuan->slug);
        $this->assertDatabaseHas('acuan_targets', [
            'acuan_program_kerja_id' => $acuan->id,
            'tahun' => 2025,
            'nilai' => '90',
            'satuan' => 'persen',
        ]);
        $this->assertSame('95 persen', $acuan->targetTahun(2026));
    }

    public function test_penawaran_mewarisi_data_acuan_saat_digenerate(): void
    {
        $m = $this->seedMasters();

        $acuan = AcuanProgramKerja::create([
            'name' => 'Riset Unggulan',
            'kelompok_acuan_id' => $m['kelompokAcuan']->id,
            'unit_kerja_id' => $m['unit']->id,
            'bidang_id' => $m['bidang']->id,
            'kategori_id' => $m['kategori']->id,
            'program_id' => $m['program']->id,
            'nilai_standar' => '5',
            'satuan_nilai_standar' => 'judul',
            'is_active' => true,
        ]);
        $acuan->targets()->create(['tahun' => $m['tahunKerja']->tahunTarget(), 'nilai' => '10', 'satuan' => 'judul']);

        (new GeneratePenawaranFromAcuan)->handle($m['kelompokAcuan'], $m['tahunKerja'], EnumModeGenerate::Baru);

        $this->assertDatabaseHas(PenawaranProgramKerja::class, [
            'acuan_program_kerja_id' => $acuan->id,
            'tahun_kerja_id' => $m['tahunKerja']->id,
            'name' => 'Riset Unggulan',
            'unit_kerja_id' => $m['unit']->id,
            'nilai_standar' => '5',
            'target' => '10 judul',
        ]);
    }

    /**
     * Penawaran hanya boleh lahir dari generate, sehingga jalur input manual ditutup.
     */
    public function test_penawaran_tidak_bisa_dibuat_manual(): void
    {
        $this->assertFalse(PenawaranProgramKerjaResource::canCreate());
        $this->assertArrayNotHasKey('create', PenawaranProgramKerjaResource::getPages());
    }
}
