<?php

namespace Tests\Feature;

use App\Enums\EnumRole;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\PermissionRegistrar;
use App\Services\UnitKerjaAktif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UnitKerjaAktifTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $unitA;

    private UnitKerja $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitA = UnitKerja::create(['name' => 'Unit A']);
        $this->unitB = UnitKerja::create(['name' => 'Unit B']);
    }

    private function pimpinanDuaUnit(): User
    {
        $user = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);
        $user->syncRoles([EnumRole::unitScopeName($this->unitB->name)]);

        return $user;
    }

    public function test_unit_bawaan_pengguna_menjadi_unit_aktif_pertama(): void
    {
        $user = $this->pimpinanDuaUnit();
        $this->actingAs($user);
        $this->startSession();

        $this->assertSame($this->unitA->id, UnitKerjaAktif::id());
        $this->assertSame([$this->unitA->id], PermissionRegistrar::permittedUnitIds($user)->all());
    }

    public function test_berganti_unit_mempersempit_cakupan_data_ke_unit_terpilih(): void
    {
        $user = $this->pimpinanDuaUnit();
        $this->actingAs($user);
        $this->startSession();

        UnitKerjaAktif::set($this->unitB->id);

        $this->assertSame($this->unitB->id, UnitKerjaAktif::id());
        $this->assertSame([$this->unitB->id], PermissionRegistrar::permittedUnitIds($user)->all());

        // Daftar unit yang boleh diakses tidak ikut menyempit — itu isi pengalihnya.
        $this->assertSame(
            [$this->unitA->id, $this->unitB->id],
            PermissionRegistrar::allPermittedUnitIds($user)->sort()->values()->all(),
        );
    }

    public function test_unit_yang_bukan_haknya_ditolak(): void
    {
        $unitLain = UnitKerja::create(['name' => 'Unit Asing']);
        $user = $this->pimpinanDuaUnit();
        $this->actingAs($user);
        $this->startSession();

        UnitKerjaAktif::set($unitLain->id);

        $this->assertSame($this->unitA->id, UnitKerjaAktif::id());
    }

    public function test_pilihan_basi_jatuh_kembali_ke_unit_bawaan(): void
    {
        $user = $this->pimpinanDuaUnit();
        $this->actingAs($user);
        $this->startSession();

        UnitKerjaAktif::set($this->unitB->id);

        // Hak atas unit B dicabut setelah pilihan tersimpan.
        $user->syncRoles([]);
        $user->unsetRelation('roles')->forgetCachedPermissions();

        $this->assertSame($this->unitA->id, UnitKerjaAktif::id());
    }

    public function test_pengalih_hanya_untuk_pengguna_berunit_ganda(): void
    {
        $satuUnit = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);
        $this->actingAs($satuUnit);
        $this->startSession();
        $this->assertFalse(UnitKerjaAktif::dapatBerganti());

        $this->actingAs($this->pimpinanDuaUnit());
        $this->assertTrue(UnitKerjaAktif::dapatBerganti());
    }

    public function test_pengguna_berakses_penuh_tidak_dibatasi_pengalih(): void
    {
        $user = $this->pimpinanDuaUnit();
        $user->givePermissionTo(Permission::findOrCreate('bypass_data_scope', 'web'));
        $this->actingAs($user);
        $this->startSession();

        $this->assertFalse(UnitKerjaAktif::dapatBerganti());
    }

    public function test_cakupan_pengguna_lain_tidak_ikut_terpengaruh_pilihan_kita(): void
    {
        $user = $this->pimpinanDuaUnit();
        $this->actingAs($user);
        $this->startSession();

        UnitKerjaAktif::set($this->unitB->id);

        $orangLain = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);

        $this->assertSame([$this->unitA->id], PermissionRegistrar::permittedUnitIds($orangLain)->all());
    }

    public function test_tanpa_session_cakupan_mencakup_semua_unit_yang_diizinkan(): void
    {
        $user = $this->pimpinanDuaUnit();

        $this->assertSame(
            [$this->unitA->id, $this->unitB->id],
            PermissionRegistrar::permittedUnitIds($user)->sort()->values()->all(),
        );
    }
}
