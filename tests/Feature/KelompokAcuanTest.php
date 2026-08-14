<?php

namespace Tests\Feature;

use App\Filament\Resources\AcuanProgramKerjas\Pages\ListAcuanProgramKerjas;
use App\Filament\Resources\KelompokAcuans\KelompokAcuanResource;
use App\Filament\Resources\KelompokAcuans\Pages\CreateKelompokAcuan;
use App\Filament\Resources\KelompokAcuans\Pages\ListKelompokAcuans;
use App\Models\AcuanProgramKerja;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\KelompokAcuan;
use App\Models\Program;
use App\Models\Setting;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KelompokAcuanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_list_and_create_pages_render(): void
    {
        Livewire::test(ListKelompokAcuans::class)->assertOk();
        Livewire::test(CreateKelompokAcuan::class)->assertOk();
    }

    public function test_view_page_with_relation_manager_renders(): void
    {
        $kelompok = KelompokAcuan::create(['name' => 'Program Kerja 2025 - 2030', 'tahun_mulai' => 2025, 'tahun_selesai' => 2030, 'is_active' => true]);

        $this->get(KelompokAcuanResource::getUrl('view', ['record' => $kelompok]))
            ->assertOk();
    }

    public function test_can_create_kelompok_acuan(): void
    {
        Livewire::test(CreateKelompokAcuan::class)
            ->fillForm([
                'name' => 'Program Kerja 2025 - 2029',
                'tahun_mulai' => 2025,
                'tahun_selesai' => 2029,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(KelompokAcuan::class, [
            'name' => 'Program Kerja 2025 - 2029',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_active' => true,
        ]);
    }

    public function test_rentang_tahun_harus_sesuai_pengaturan_sistem(): void
    {
        Setting::set(Setting::TAHUN_PER_PERIODE, 5);

        Livewire::test(CreateKelompokAcuan::class)
            ->fillForm([
                'name' => 'Program Kerja 2025 - 2030',
                'tahun_mulai' => 2025,
                'tahun_selesai' => 2030,
            ])
            ->call('create')
            ->assertHasFormErrors(['tahun_selesai']);

        $this->assertDatabaseCount(KelompokAcuan::class, 0);
    }

    public function test_tahun_selesai_mengikuti_pengaturan_yang_diubah(): void
    {
        Setting::set(Setting::TAHUN_PER_PERIODE, 4);

        Livewire::test(CreateKelompokAcuan::class)
            ->fillForm(['name' => 'Program Kerja 2025 - 2028', 'tahun_mulai' => 2025, 'tahun_selesai' => 2028])
            ->call('create')
            ->assertHasNoFormErrors();

        $kelompok = KelompokAcuan::firstWhere('name', 'Program Kerja 2025 - 2028');
        $this->assertSame([2025, 2026, 2027, 2028], $kelompok->tahunList());
    }

    public function test_only_one_kelompok_stays_active(): void
    {
        $first = KelompokAcuan::create(['name' => 'RPJM 2020 - 2025', 'tahun_mulai' => 2020, 'tahun_selesai' => 2025, 'is_active' => true]);
        $second = KelompokAcuan::create(['name' => 'RPJM 2025 - 2030', 'tahun_mulai' => 2025, 'tahun_selesai' => 2030, 'is_active' => true]);

        $this->assertFalse($first->refresh()->is_active);
        $this->assertTrue($second->refresh()->is_active);
        $this->assertSame($second->id, KelompokAcuan::active()?->id);
    }

    public function test_acuan_list_defaults_to_active_kelompok(): void
    {
        $unit = UnitKerja::create(['name' => 'Unit A']);
        $bidang = Bidang::create(['code' => 'B1', 'name' => 'B']);
        $kategori = Kategori::create(['code' => 'K1', 'name' => 'K']);
        $program = Program::create(['name' => 'P']);

        $aktif = KelompokAcuan::create(['name' => 'Aktif 2025 - 2030', 'tahun_mulai' => 2025, 'tahun_selesai' => 2030, 'is_active' => true]);
        $lama = KelompokAcuan::create(['name' => 'Lama 2020 - 2025', 'tahun_mulai' => 2020, 'tahun_selesai' => 2025, 'is_active' => false]);

        $base = ['unit_kerja_id' => $unit->id, 'bidang_id' => $bidang->id, 'kategori_id' => $kategori->id, 'program_id' => $program->id, 'is_active' => true];
        $acuanAktif = AcuanProgramKerja::create([...$base, 'name' => 'Acuan Aktif', 'kelompok_acuan_id' => $aktif->id]);
        $acuanLama = AcuanProgramKerja::create([...$base, 'name' => 'Acuan Lama', 'kelompok_acuan_id' => $lama->id]);

        Livewire::test(ListAcuanProgramKerjas::class)
            ->assertCanSeeTableRecords([$acuanAktif])
            ->assertCanNotSeeTableRecords([$acuanLama]);
    }
}
