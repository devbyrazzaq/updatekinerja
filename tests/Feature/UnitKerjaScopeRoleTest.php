<?php

namespace Tests\Feature;

use App\Enums\EnumRole;
use App\Filament\Pages\BukuAnggaran;
use App\Filament\Pages\MonitoringProgramKerja;
use App\Filament\Pages\MonitoringRealisasi;
use App\Filament\Pages\PerbandinganMonitoring;
use App\Filament\Resources\RekeningBanks\RekeningBankResource;
use App\Filament\Widgets\AksiCepatWidget;
use App\Filament\Widgets\PenyerapanBulananWidget;
use App\Filament\Widgets\PeriodeBerjalanWidget;
use App\Filament\Widgets\PintasanMenuWidget;
use App\Filament\Widgets\RingkasanAnggaranWidget;
use App\Filament\Widgets\RingkasanProgramKerjaWidget;
use App\Filament\Widgets\SisaWaktuTahunKerjaWidget;
use App\Filament\Widgets\TentangSistemWidget;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\PermissionRegistrar;
use App\Services\UnitKerjaAktif;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitKerjaScopeRoleSeeder;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnitKerjaScopeRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_all_unit_kerja_from_legacy_database(): void
    {
        $this->seed(UnitKerjaSeeder::class);

        $this->assertSame(34, UnitKerja::count());
        $this->assertDatabaseHas('unit_kerjas', [
            'slug' => 'lembaga-penelitian-dan-pengabdian-kepada-masyarakat-lppm',
            'name' => 'Lembaga Penelitian dan Pengabdian kepada Masyarakat ( LPPM )',
            'is_active' => true,
        ]);
    }

    public function test_unit_kerja_seeder_is_idempotent(): void
    {
        $this->seed(UnitKerjaSeeder::class);
        $this->seed(UnitKerjaSeeder::class);

        $this->assertSame(34, UnitKerja::count());
    }

    public function test_scope_role_is_created_for_each_unit_kerja(): void
    {
        $this->seed(UnitKerjaSeeder::class);
        $this->seed(UnitKerjaScopeRoleSeeder::class);

        $unit = UnitKerja::where('slug', 'perpustakaan')->firstOrFail();
        $role = Role::findByName(EnumRole::unitScopeName('Perpustakaan'), 'web');

        $this->assertSame(
            [PermissionRegistrar::dataScopePermissionPrefix('unit').$unit->id],
            $role->permissions->pluck('name')->all(),
        );

        $this->assertSame(34, Role::where('name', 'like', EnumRole::UNIT_SCOPE_PREFIX.'%')->count());
    }

    public function test_creating_unit_kerja_creates_its_scope_role(): void
    {
        $unit = UnitKerja::create(['name' => 'Unit Baru']);

        $role = Role::findByName(EnumRole::unitScopeName('Unit Baru'), 'web');

        $this->assertSame(
            [PermissionRegistrar::dataScopePermissionPrefix('unit').$unit->id],
            $role->permissions->pluck('name')->all(),
        );
    }

    public function test_renaming_unit_kerja_renames_its_scope_role(): void
    {
        $unit = UnitKerja::create(['name' => 'Unit Lama']);
        $unit->update(['name' => 'Unit Berganti Nama']);

        $this->assertTrue(Role::where('name', EnumRole::unitScopeName('Unit Berganti Nama'))->exists());
        $this->assertFalse(Role::where('name', EnumRole::unitScopeName('Unit Lama'))->exists());
    }

    public function test_scope_roles_stack_so_a_user_can_reach_several_units(): void
    {
        [$unitA, $unitB, $unitC] = $this->tigaUnit();

        $user = User::factory()->create(['unit_kerja_id' => null]);
        $user->syncRoles([
            EnumRole::unitScopeName($unitA->name),
            EnumRole::unitScopeName($unitB->name),
        ]);

        $this->actingAs($user);

        $ids = PermissionRegistrar::allPermittedUnitIds($user)->sort()->values()->all();

        $this->assertSame([$unitA->id, $unitB->id], $ids);
        $this->assertArrayNotHasKey($unitC->id, UnitKerjaAktif::opsi($user));
    }

    public function test_pimpinan_unit_only_reaches_pelaksanaan_pemasukan_perencanaan_and_extra_menus(): void
    {
        $this->seed(UnitKerjaSeeder::class);
        $this->seed(RoleSeeder::class);

        $granted = Role::findByName(EnumRole::PimpinanUnit->value, 'web')
            ->permissions
            ->pluck('name')
            ->all();

        $diizinkan = [
            ...PermissionRegistrar::permissionNamesForNavGroups(['Pelaksanaan', 'Pemasukan', 'Perencanaan']),
            // Menu satuan di luar ketiga grup: rekening bank (tanpa hapus massal), buku
            // anggaran unit, tiga menu monitoring, dan widget dashboard.
            ...PermissionRegistrar::permissionNamesForMenus([
                RekeningBankResource::class => ['view_any', 'view', 'create', 'update', 'delete'],
                BukuAnggaran::class => null,
                MonitoringProgramKerja::class => null,
                MonitoringRealisasi::class => null,
                PerbandinganMonitoring::class => null,
                TentangSistemWidget::class => null,
                PeriodeBerjalanWidget::class => null,
                SisaWaktuTahunKerjaWidget::class => null,
                RingkasanAnggaranWidget::class => null,
                RingkasanProgramKerjaWidget::class => null,
                AksiCepatWidget::class => null,
                PintasanMenuWidget::class => null,
                PenyerapanBulananWidget::class => null,
            ]),
        ];

        sort($granted);
        sort($diizinkan);

        $this->assertSame($diizinkan, $granted);
        $this->assertNotEmpty($granted);

        $this->assertContains('view_any_rekening_bank', $granted);
        $this->assertContains('create_rekening_bank', $granted);
        $this->assertContains('view_page_buku_anggaran', $granted);
        $this->assertContains('view_page_monitoring_program_kerja', $granted);
        $this->assertContains('view_page_monitoring_realisasi', $granted);

        // Menu lain di luar ketiga grup itu — termasuk master data dan manajemen akses
        // — tidak boleh ikut terbawa, begitu pula hapus massal rekening bank dan
        // pintasan melihat seluruh unit / lewati pembatasan data.
        $this->assertContains('delete_rekening_bank', $granted);
        $this->assertNotContains('delete_any_rekening_bank', $granted);
        $this->assertNotContains('view_all_unit_data', $granted);
        $this->assertNotContains('view_any_unit_kerja', $granted);
        $this->assertNotContains('view_any_role', $granted);
        $this->assertNotContains('view_any_pagu_anggaran', $granted);
        $this->assertNotContains('bypass_data_scope', $granted);
    }

    /**
     * Urutan DatabaseSeeder harus menyemai unit kerja sebelum role, sebab role
     * pembatas data dibangun dari daftar unit yang ada.
     */
    public function test_database_seeder_produces_pimpinan_unit_with_two_units(): void
    {
        $this->seed(DatabaseSeeder::class);

        $pimpinan = User::where('username', 'pimpinanunit')->firstOrFail();

        $this->assertTrue($pimpinan->hasRole(EnumRole::PimpinanUnit->value));
        $this->assertCount(2, PermissionRegistrar::allPermittedUnitIds($pimpinan));
        $this->assertSame(34, UnitKerja::count());
    }

    /**
     * @return array<int, UnitKerja>
     */
    private function tigaUnit(): array
    {
        return [
            UnitKerja::create(['name' => 'Unit A']),
            UnitKerja::create(['name' => 'Unit B']),
            UnitKerja::create(['name' => 'Unit C']),
        ];
    }
}
