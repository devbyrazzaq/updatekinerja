<?php

namespace Tests\Feature;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Resources\PengajuanProgramKerjas\PengajuanProgramKerjaResource;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\PermissionRegistrar;
use App\Services\UnitKerjaAktif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DataScopeTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $unitA;

    private UnitKerja $unitB;

    private PengajuanProgramKerja $pengajuanA;

    private PengajuanProgramKerja $pengajuanB;

    protected function setUp(): void
    {
        parent::setUp();

        $periode = Periode::create(['name' => 'P1', 'start_datetime' => now(), 'end_datetime' => now()->addYear()]);
        $tahunKerja = TahunKerja::create(['periode_id' => $periode->id, 'name' => 'TA 2026', 'start_datetime' => now(), 'end_datetime' => now()->addYear(), 'status' => EnumStatusTahunKerja::Berjalan]);
        $bidang = Bidang::create(['code' => 'B1', 'name' => 'Akademik']);
        $kategori = Kategori::create(['code' => 'K1', 'name' => 'Pendidikan']);
        $program = Program::create(['name' => 'Tridharma']);

        $this->unitA = UnitKerja::create(['name' => 'Unit A']);
        $this->unitB = UnitKerja::create(['name' => 'Unit B']);

        $this->pengajuanA = $this->makePengajuan($this->unitA, $tahunKerja, $bidang, $kategori, $program);
        $this->pengajuanB = $this->makePengajuan($this->unitB, $tahunKerja, $bidang, $kategori, $program);
    }

    private function makePengajuan(UnitKerja $unit, TahunKerja $tk, Bidang $b, Kategori $k, Program $p): PengajuanProgramKerja
    {
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $tk->id, 'unit_kerja_id' => $unit->id,
            'bidang_id' => $b->id, 'kategori_id' => $k->id, 'program_id' => $p->id,
            'name' => 'Prokerja '.$unit->name, 'is_active' => true,
        ]);

        return PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $unit->id,
            'alokasi_anggaran' => 1000,
            'status' => EnumStatusPengajuan::Diajukan,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function visibleIdsFor(User $user): array
    {
        $this->actingAs($user);

        return PengajuanProgramKerjaResource::getEloquentQuery()->pluck('id')->all();
    }

    public function test_user_with_own_unit_sees_only_that_unit(): void
    {
        $user = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);

        $ids = $this->visibleIdsFor($user);

        $this->assertContains($this->pengajuanA->id, $ids);
        $this->assertNotContains($this->pengajuanB->id, $ids);
    }

    public function test_user_without_unit_or_scope_sees_nothing(): void
    {
        $user = User::factory()->create(['unit_kerja_id' => null]);

        $this->assertSame([], $this->visibleIdsFor($user));
    }

    /**
     * Permission scope menambah unit yang BOLEH dijangkau, tetapi data yang tampil
     * tetap satu unit pada satu waktu — unit aktif, yang bawaannya unit pengguna.
     */
    public function test_scope_permission_grants_access_to_extra_unit(): void
    {
        $user = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);
        $permission = Permission::findOrCreate(PermissionRegistrar::dataScopePermissionPrefix('unit').$this->unitB->id, 'web');
        $user->givePermissionTo($permission);

        $this->assertSame(
            [$this->unitA->id, $this->unitB->id],
            PermissionRegistrar::allPermittedUnitIds($user)->sort()->values()->all(),
        );

        $ids = $this->visibleIdsFor($user);

        $this->assertContains($this->pengajuanA->id, $ids);
        $this->assertNotContains($this->pengajuanB->id, $ids);
    }

    /**
     * Berganti unit lewat pengalih di topbar memindahkan seluruh cakupan data.
     */
    public function test_switching_active_unit_moves_the_visible_data(): void
    {
        $user = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);
        $user->givePermissionTo(Permission::findOrCreate(PermissionRegistrar::dataScopePermissionPrefix('unit').$this->unitB->id, 'web'));

        $this->actingAs($user);
        $this->startSession();

        UnitKerjaAktif::set($this->unitB->id);

        $ids = PengajuanProgramKerjaResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($this->pengajuanB->id, $ids);
        $this->assertNotContains($this->pengajuanA->id, $ids);
    }

    public function test_privileged_user_sees_all(): void
    {
        $user = User::factory()->create(['unit_kerja_id' => null]);
        $user->givePermissionTo(Permission::findOrCreate('bypass_data_scope', 'web'));

        $ids = $this->visibleIdsFor($user);

        $this->assertContains($this->pengajuanA->id, $ids);
        $this->assertContains($this->pengajuanB->id, $ids);
    }
}
