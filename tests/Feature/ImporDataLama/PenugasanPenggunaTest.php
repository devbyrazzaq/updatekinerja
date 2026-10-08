<?php

namespace Tests\Feature\ImporDataLama;

use App\Enums\EnumRole;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\ImporDataLama\PenugasanPengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Penugasan LAMADU (multi role + unit utama + unit tambahan) diterapkan pada akun
 * sistem ini sebagai role fungsional, kolom unit kerja, dan role pembatas data.
 */
class PenugasanPenggunaTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $unitUtama;

    private UnitKerja $unitTambahan;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (EnumRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        $this->unitUtama = UnitKerja::factory()->create(['name' => 'Biro Administrasi Umum']);
        $this->unitTambahan = UnitKerja::factory()->create(['name' => 'Sumber Daya Insan']);
    }

    public function test_menerapkan_role_unit_utama_dan_unit_tambahan(): void
    {
        $user = User::factory()->create();

        app(PenugasanPengguna::class)->terapkan(
            $user,
            [EnumRole::PimpinanUnit->value],
            $this->unitUtama->id,
            [$this->unitUtama->id, $this->unitTambahan->id],
        );

        $user->refresh();

        $this->assertSame($this->unitUtama->id, $user->unit_kerja_id);
        $this->assertEqualsCanonicalizing([
            EnumRole::PimpinanUnit->value,
            EnumRole::unitScopeName('Biro Administrasi Umum'),
            EnumRole::unitScopeName('Sumber Daya Insan'),
        ], $user->getRoleNames()->all());
    }

    public function test_role_persona_dipertahankan_dan_penugasan_lama_dicabut(): void
    {
        $user = User::factory()->create(['unit_kerja_id' => $this->unitTambahan->id]);
        $user->assignRole([
            EnumRole::Dosen->value,
            EnumRole::UnitKerja->value,
            Role::findOrCreate(EnumRole::unitScopeName('Sumber Daya Insan'), 'web'),
        ]);

        app(PenugasanPengguna::class)->terapkan(
            $user,
            [EnumRole::WakilRektorI->value],
            $this->unitUtama->id,
            [],
        );

        $user->refresh();

        $this->assertSame($this->unitUtama->id, $user->unit_kerja_id);
        $this->assertEqualsCanonicalizing([
            EnumRole::Dosen->value,
            EnumRole::WakilRektorI->value,
            EnumRole::unitScopeName('Biro Administrasi Umum'),
        ], $user->getRoleNames()->all());
    }

    public function test_tanpa_unit_utama_kolom_unit_kerja_tidak_diubah(): void
    {
        $user = User::factory()->create(['unit_kerja_id' => $this->unitTambahan->id]);

        app(PenugasanPengguna::class)->terapkan($user, [EnumRole::PimpinanUnit->value], null, []);

        $user->refresh();

        $this->assertSame($this->unitTambahan->id, $user->unit_kerja_id);
        $this->assertSame([EnumRole::PimpinanUnit->value], $user->getRoleNames()->all());
    }

    public function test_unit_utama_yang_tidak_dikenal_diabaikan(): void
    {
        $user = User::factory()->create(['unit_kerja_id' => null]);

        app(PenugasanPengguna::class)->terapkan($user, [EnumRole::BiroKeuangan->value], 9999, [9999]);

        $user->refresh();

        $this->assertNull($user->unit_kerja_id);
        $this->assertSame([EnumRole::BiroKeuangan->value], $user->getRoleNames()->all());
    }

    public function test_akun_tanpa_persona_diberi_persona_bawaan(): void
    {
        $user = User::factory()->create();

        app(PenugasanPengguna::class)->terapkan($user, [EnumRole::Rektor->value], null, [], EnumRole::Dosen);

        $this->assertEqualsCanonicalizing(
            [EnumRole::Dosen->value, EnumRole::Rektor->value],
            $user->refresh()->getRoleNames()->all(),
        );
    }

    public function test_persona_yang_sudah_ada_tidak_diganti_persona_bawaan(): void
    {
        $user = User::factory()->create();
        $user->assignRole(EnumRole::Admin->value);

        app(PenugasanPengguna::class)->terapkan($user, [EnumRole::PimpinanUnit->value], null, [], EnumRole::Dosen);

        $this->assertEqualsCanonicalizing(
            [EnumRole::Admin->value, EnumRole::PimpinanUnit->value],
            $user->refresh()->getRoleNames()->all(),
        );
    }
}
