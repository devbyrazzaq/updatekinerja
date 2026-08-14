<?php

namespace Tests\Feature;

use App\Enums\EnumRole;
use App\Filament\Pages\PengaturanSistem;
use App\Models\Setting;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\UnitKerjaAktif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BrandPanelTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $unitA;

    private UnitKerja $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Setting::BRAND_LOGO_DISK);

        $this->unitA = UnitKerja::create(['name' => 'Fakultas Teknik']);
        $this->unitB = UnitKerja::create(['name' => 'Fakultas Kesehatan']);
    }

    public function test_brand_memakai_nilai_bawaan_saat_belum_pernah_disimpan(): void
    {
        $this->assertSame('SIM KINERJA', Setting::brandNama());
        $this->assertSame('Universitas Muhammadiyah Lamongan', Setting::brandInstansi());
        $this->assertNull(Setting::brandLogoPath());
        $this->assertNull(Setting::brandLogoUrl());
    }

    public function test_nama_instansi_dipakai_saat_pengguna_melihat_seluruh_unit(): void
    {
        $user = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);
        $user->givePermissionTo(Permission::findOrCreate('bypass_data_scope', 'web'));

        $this->actingAs($user);
        $this->startSession();

        $this->assertSame('Universitas Muhammadiyah Lamongan', UnitKerjaAktif::namaCakupan());
    }

    public function test_nama_unit_aktif_dipakai_saat_cakupan_pengguna_dibatasi(): void
    {
        $user = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);
        $user->syncRoles([EnumRole::unitScopeName($this->unitB->name)]);

        $this->actingAs($user);
        $this->startSession();

        $this->assertSame('Fakultas Teknik', UnitKerjaAktif::namaCakupan());

        // Berganti unit lewat pengalih ikut mengganti keterangan pada brand.
        UnitKerjaAktif::set($this->unitB->id);

        $this->assertSame('Fakultas Kesehatan', UnitKerjaAktif::namaCakupan());
    }

    public function test_nama_instansi_dipakai_saat_pengguna_belum_punya_unit(): void
    {
        Setting::set(Setting::BRAND_INSTANSI, 'Universitas Contoh');

        $this->actingAs(User::factory()->create(['unit_kerja_id' => null]));
        $this->startSession();

        $this->assertSame('Universitas Contoh', UnitKerjaAktif::namaCakupan());
    }

    public function test_identitas_aplikasi_dapat_disimpan_lewat_pengaturan_sistem(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(PengaturanSistem::class)
            ->assertFormSet([
                Setting::BRAND_NAMA => 'SIM KINERJA',
                Setting::BRAND_INSTANSI => 'Universitas Muhammadiyah Lamongan',
            ])
            ->fillForm([
                Setting::BRAND_NAMA => 'SIM MUTU',
                Setting::BRAND_INSTANSI => 'Universitas Contoh',
                Setting::BRAND_LOGO => [UploadedFile::fake()->image('logo.png')],
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertSame('SIM MUTU', Setting::brandNama());
        $this->assertSame('Universitas Contoh', Setting::brandInstansi());

        $logo = Setting::brandLogoPath();

        $this->assertNotNull($logo);
        Storage::disk(Setting::BRAND_LOGO_DISK)->assertExists($logo);
        $this->assertNotNull(Setting::brandLogoUrl());
    }

    public function test_identitas_aplikasi_wajib_diisi(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(PengaturanSistem::class)
            ->fillForm([
                Setting::BRAND_NAMA => '',
                Setting::BRAND_INSTANSI => '',
            ])
            ->call('save')
            ->assertHasFormErrors([
                Setting::BRAND_NAMA => 'required',
                Setting::BRAND_INSTANSI => 'required',
            ]);
    }

    public function test_brand_tanpa_logo_tetap_sah_dan_tidak_menghasilkan_url(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(PengaturanSistem::class)
            ->fillForm([Setting::BRAND_LOGO => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull(Setting::brandLogoPath());
        $this->assertNull(Setting::brandLogoUrl());
    }

    public function test_panel_merender_brand_beserta_keterangan_cakupan(): void
    {
        Setting::set(Setting::BRAND_NAMA, 'SIM KINERJA');

        $user = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);
        $user->syncRoles([EnumRole::unitScopeName($this->unitB->name)]);

        $this->actingAs($user)
            ->get(filament()->getPanel('app')->getUrl())
            ->assertOk()
            ->assertSee('SIM KINERJA')
            ->assertSee('Fakultas Teknik');
    }

    public function test_logo_yang_berkasnya_hilang_tidak_dirender(): void
    {
        Setting::set(Setting::BRAND_LOGO, 'brand/sudah-terhapus.png');

        $this->assertSame('brand/sudah-terhapus.png', Setting::brandLogoPath());
        $this->assertNull(Setting::brandLogoUrl());
    }
}
