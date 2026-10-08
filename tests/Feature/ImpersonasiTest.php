<?php

namespace Tests\Feature;

use App\Enums\EnumPermission;
use App\Enums\EnumRole;
use App\Filament\Resources\Dosens\DosenResource;
use App\Filament\Resources\Dosens\Pages\ListDosens;
use App\Filament\Resources\Dosens\Pages\ViewDosen;
use App\Models\User;
use App\Services\Impersonasi;
use App\Services\UnitKerjaAktif;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Masuk Sebagai" pada menu Pengguna: admin berganti akun menjadi pengguna lain
 * dan kembali lagi lewat banner di bawah topbar.
 */
class ImpersonasiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $dosen;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (EnumRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        $this->admin = $this->penggunaDenganPermission('adminsatu', [
            DosenResource::getPermissionName('view_any'),
            DosenResource::getPermissionName('view'),
            DosenResource::getPermissionName('impersonate'),
        ]);

        $this->dosen = $this->dosen('dosensatu');

        $this->actingAs($this->admin);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function penggunaDenganPermission(string $username, array $permissions): User
    {
        $user = User::factory()->create(['username' => $username]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function dosen(string $username, array $attributes = []): User
    {
        $user = User::factory()->create(['username' => $username, ...$attributes]);
        $user->assignRole(EnumRole::Dosen->value);

        return $user;
    }

    public function test_permission_masuk_sebagai_terdaftar_per_menu_persona(): void
    {
        $this->assertArrayHasKey('impersonate_dosen', DosenResource::getPermissionDefinitions());
    }

    public function test_masuk_sebagai_mengganti_akun_dan_mengarahkan_ke_panel(): void
    {
        Livewire::test(ListDosens::class)
            ->assertActionVisible(TestAction::make('masukSebagai')->table($this->dosen))
            ->callAction(TestAction::make('masukSebagai')->table($this->dosen))
            ->assertRedirect(Filament::getUrl());

        $this->assertAuthenticatedAs($this->dosen);
        $this->assertTrue(Impersonasi::aktif());
        $this->assertTrue(Impersonasi::penggunaAsli()->is($this->admin));
    }

    public function test_aksi_juga_tersedia_di_halaman_detail(): void
    {
        Livewire::test(ViewDosen::class, ['record' => $this->dosen->username])
            ->callAction('masukSebagai')
            ->assertRedirect(Filament::getUrl());

        $this->assertAuthenticatedAs($this->dosen);
    }

    public function test_aksi_tersembunyi_tanpa_permission_masuk_sebagai(): void
    {
        $this->actingAs($this->penggunaDenganPermission('operator', [
            DosenResource::getPermissionName('view_any'),
            DosenResource::getPermissionName('view'),
        ]));

        Livewire::test(ListDosens::class)
            ->assertActionHidden(TestAction::make('masukSebagai')->table($this->dosen));
    }

    public function test_aksi_tersembunyi_untuk_akun_nonaktif_dan_berakses_penuh(): void
    {
        $nonaktif = $this->dosen('dosennonaktif', ['is_active' => false]);
        $aksesPenuh = $this->dosen('dosenpenuh');
        $aksesPenuh->givePermissionTo(Permission::findOrCreate(EnumPermission::BypassDataScope->value, 'web'));

        Livewire::test(ListDosens::class)
            ->assertActionHidden(TestAction::make('masukSebagai')->table($nonaktif))
            ->assertActionHidden(TestAction::make('masukSebagai')->table($aksesPenuh));
    }

    public function test_tidak_bisa_masuk_sebagai_diri_sendiri(): void
    {
        $this->assertFalse(Impersonasi::bolehMasukSebagai($this->admin));
    }

    public function test_tidak_bisa_masuk_sebagai_secara_bertingkat(): void
    {
        $dosenLain = $this->dosen('dosendua');
        $this->dosen->givePermissionTo(Permission::findOrCreate(DosenResource::getPermissionName('impersonate'), 'web'));

        Impersonasi::mulai($this->dosen, url('/app/dosens'));

        $this->assertFalse(Impersonasi::bolehMasukSebagai($dosenLain));
    }

    /**
     * Middleware AuthenticateSession mencocokkan hash password di session dengan
     * akun yang login; tanpa pembaruan hash, request berikutnya ter-logout. Password
     * dosen dibedakan karena factory memakai satu hash yang sama untuk semua akun.
     */
    public function test_panel_tetap_login_sebagai_target_dan_menampilkan_banner(): void
    {
        $this->dosen->update(['password' => 'rahasia-dosen']);

        $this->get(Filament::getUrl());

        Impersonasi::mulai($this->dosen, url('/app/dosens'));

        $this->get(Filament::getUrl())
            ->assertOk()
            ->assertSee('Anda sedang masuk sebagai')
            ->assertSee($this->dosen->getFullName())
            ->assertSee('Kembali ke akun saya');

        $this->assertAuthenticatedAs($this->dosen);
    }

    public function test_banner_tidak_tampil_di_luar_mode_impersonasi(): void
    {
        $this->get(Filament::getUrl())
            ->assertOk()
            ->assertDontSee('Anda sedang masuk sebagai');
    }

    public function test_kembali_ke_akun_asli_dan_halaman_asal(): void
    {
        $this->dosen->update(['password' => 'rahasia-dosen']);
        $this->withSession([UnitKerjaAktif::SESSION_KEY => 7]);

        Impersonasi::mulai($this->dosen, url('/app/dosens'));

        $this->assertNull(session(UnitKerjaAktif::SESSION_KEY));

        $this->post(Filament::getDefaultPanel()->route('impersonasi.kembali'))
            ->assertRedirect(url('/app/dosens'));

        $this->assertAuthenticatedAs($this->admin);
        $this->assertFalse(Impersonasi::aktif());
        $this->assertSame(7, session(UnitKerjaAktif::SESSION_KEY));

        $this->get(Filament::getUrl())->assertOk();
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_url_kembali_di_luar_aplikasi_diabaikan(): void
    {
        Impersonasi::mulai($this->dosen, 'https://contoh-luar.test/jebakan');

        $this->post(Filament::getDefaultPanel()->route('impersonasi.kembali'))
            ->assertRedirect(Filament::getUrl());
    }

    public function test_kembali_tanpa_impersonasi_tidak_mengubah_akun(): void
    {
        $this->post(Filament::getDefaultPanel()->route('impersonasi.kembali'))
            ->assertRedirect(Filament::getUrl());

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_akun_asli_yang_dinonaktifkan_mengakhiri_sesi(): void
    {
        Impersonasi::mulai($this->dosen, url('/app/dosens'));

        $this->admin->update(['is_active' => false]);

        $this->post(Filament::getDefaultPanel()->route('impersonasi.kembali'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
