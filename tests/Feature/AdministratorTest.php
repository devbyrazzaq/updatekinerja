<?php

namespace Tests\Feature;

use App\Enums\EnumPermission;
use App\Enums\EnumRole;
use App\Filament\Resources\Administrators\AdministratorResource;
use App\Filament\Resources\Administrators\Pages\CreateAdministrator;
use App\Filament\Resources\Administrators\Pages\EditAdministrator;
use App\Filament\Resources\Administrators\Pages\ListAdministrators;
use App\Filament\Resources\Administrators\Pages\ViewAdministrator;
use App\Filament\Resources\Dosens\DosenResource;
use App\Filament\Resources\Dosens\Pages\ListDosens;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Menu Administrator memuat akun Admin dan Super Admin, beserta aksi akun bersama
 * (Ubah Username, Reset Kata Sandi) yang juga tersedia di menu persona lainnya.
 */
class AdministratorTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (EnumRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        Role::findByName(EnumRole::SuperAdmin->value, 'web')
            ->givePermissionTo(Permission::findOrCreate(EnumPermission::BypassDataScope->value, 'web'));

        $this->superAdmin = $this->akunDenganRole('superadmin', EnumRole::SuperAdmin);
        $this->admin = $this->akunDenganRole('adminsatu', EnumRole::Admin);

        $this->actingAs($this->superAdmin);
    }

    private function akunDenganRole(string $username, EnumRole $role, array $atribut = []): User
    {
        $user = User::factory()->create(['username' => $username, ...$atribut]);
        $user->assignRole($role->value);

        return $user;
    }

    public function test_menu_berada_di_grup_manajemen_akses(): void
    {
        $this->assertSame('Manajemen Akses', AdministratorResource::getNavigationGroup());
    }

    public function test_menu_hanya_memuat_akun_admin_dan_super_admin(): void
    {
        $dosen = $this->akunDenganRole('dosensatu', EnumRole::Dosen);

        Livewire::test(ListAdministrators::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$this->superAdmin, $this->admin])
            ->assertCanNotSeeTableRecords([$dosen]);
    }

    public function test_membuat_akun_admin_melekatkan_jenis_akun_terpilih(): void
    {
        Livewire::test(CreateAdministrator::class)
            ->fillForm([
                'name' => 'Admin Baru',
                'username' => 'adminbaru',
                'birth_date' => '1990-01-07',
                'persona_role' => EnumRole::Admin->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $akun = User::where('username', 'adminbaru')->firstOrFail();

        $this->assertTrue($akun->hasRole(EnumRole::Admin->value));
        $this->assertFalse($akun->hasRole(EnumRole::SuperAdmin->value));
        $this->assertTrue(Hash::check('adminbaru07', $akun->password));
    }

    public function test_jenis_akun_wajib_dipilih(): void
    {
        Livewire::test(CreateAdministrator::class)
            ->fillForm(['name' => 'Admin Baru', 'username' => 'adminbaru', 'persona_role' => null])
            ->call('create')
            ->assertHasFormErrors(['persona_role' => 'required']);
    }

    /**
     * Pengguna tanpa akses penuh tidak dapat membuat akun Super Admin, sekalipun
     * mengirim nilainya langsung.
     */
    public function test_pengguna_tanpa_akses_penuh_tidak_dapat_membuat_super_admin(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateAdministrator::class)
            ->fillForm([
                'name' => 'Penyusup',
                'username' => 'penyusup',
                'persona_role' => EnumRole::SuperAdmin->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['persona_role']);

        $this->assertDatabaseMissing(User::class, ['username' => 'penyusup']);
    }

    public function test_super_admin_dapat_mengubah_jenis_akun_admin(): void
    {
        Livewire::test(EditAdministrator::class, ['record' => $this->admin->getRouteKey()])
            ->assertSchemaStateSet(['persona_role' => EnumRole::Admin->value])
            ->fillForm(['persona_role' => EnumRole::SuperAdmin->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->admin->refresh();
        $this->assertTrue($this->admin->hasRole(EnumRole::SuperAdmin->value));
        $this->assertFalse($this->admin->hasRole(EnumRole::Admin->value));
    }

    /**
     * Jenis akun sendiri dikunci, dan menyimpan profil sendiri tidak mencabutnya.
     */
    public function test_jenis_akun_sendiri_tetap_saat_disimpan(): void
    {
        Livewire::test(EditAdministrator::class, ['record' => $this->superAdmin->getRouteKey()])
            ->assertFormFieldDisabled('persona_role')
            ->fillForm(['name' => 'Nama Baru'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($this->superAdmin->refresh()->hasRole(EnumRole::SuperAdmin->value));
    }

    /**
     * Akun berakses penuh hanya dapat dikelola oleh pengguna berakses penuh.
     */
    public function test_admin_tidak_dapat_mengelola_akun_super_admin(): void
    {
        $adminLain = $this->akunDenganRole('adminlain', EnumRole::Admin);
        $this->actingAs($this->admin);

        // Tabel disaring ke satu akun: pada Livewire::test, aksi dalam ActionGroup
        // dapat membawa record baris terakhir yang dirender.
        Livewire::test(ListAdministrators::class)
            ->searchTable('superadmin')
            ->assertActionHidden(TestAction::make('edit')->table($this->superAdmin))
            ->assertActionHidden(TestAction::make('delete')->table($this->superAdmin))
            ->assertActionHidden(TestAction::make('ubahUsername')->table($this->superAdmin))
            ->assertActionHidden(TestAction::make('resetKataSandi')->table($this->superAdmin));

        Livewire::test(ListAdministrators::class)
            ->searchTable('adminlain')
            ->assertActionVisible(TestAction::make('edit')->table($adminLain))
            ->assertActionVisible(TestAction::make('delete')->table($adminLain));

        $this->get(AdministratorResource::getUrl('edit', ['record' => $this->superAdmin]))->assertForbidden();
    }

    public function test_akun_sendiri_tidak_dapat_dihapus(): void
    {
        Livewire::test(ListAdministrators::class)
            ->searchTable('superadmin')
            ->assertActionHidden(TestAction::make('delete')->table($this->superAdmin));

        Livewire::test(ListAdministrators::class)
            ->searchTable('adminsatu')
            ->assertActionVisible(TestAction::make('delete')->table($this->admin));

        $this->assertSame('Akun yang sedang Anda pakai tidak dapat dihapus.', $this->superAdmin->getDeletionRestrictionReason());
    }

    public function test_username_dapat_diubah_lewat_aksi(): void
    {
        Livewire::test(ListAdministrators::class)
            ->callAction(TestAction::make('ubahUsername')->table($this->admin), ['username' => 'adminbaru'])
            ->assertHasNoActionErrors()
            ->assertNotified('Username berhasil diubah');

        $this->assertSame('adminbaru', $this->admin->refresh()->username);
    }

    /**
     * Username adalah alamat halaman detail akun, jadi halaman dialihkan ke alamat barunya.
     */
    public function test_ubah_username_dari_halaman_detail_mengalihkan_ke_alamat_baru(): void
    {
        Livewire::test(ViewAdministrator::class, ['record' => $this->admin->getRouteKey()])
            ->callAction('ubahUsername', ['username' => 'adminbaru'])
            ->assertHasNoActionErrors()
            ->assertRedirect(AdministratorResource::getUrl('view', ['record' => 'adminbaru']));
    }

    public function test_username_baru_wajib_unik(): void
    {
        Livewire::test(ListAdministrators::class)
            ->callAction(TestAction::make('ubahUsername')->table($this->admin), ['username' => 'superadmin'])
            ->assertHasActionErrors(['username' => 'unique']);

        $this->assertSame('adminsatu', $this->admin->refresh()->username);
    }

    public function test_reset_kata_sandi_mengembalikan_ke_kata_sandi_awal(): void
    {
        $this->admin->update(['birth_date' => '1990-01-07', 'password' => 'rahasia-lama']);

        $this->jalankanAksiCaptcha(
            Livewire::test(ListAdministrators::class),
            TestAction::make('resetKataSandi')->table($this->admin),
        )
            ->assertHasNoActionErrors()
            ->assertNotified('Kata sandi berhasil di-reset');

        $this->assertTrue(Hash::check('adminsatu07', $this->admin->refresh()->password));
    }

    public function test_reset_kata_sandi_ditolak_saat_jawaban_captcha_salah(): void
    {
        $this->admin->update(['password' => 'rahasia-lama']);

        $this->jalankanAksiCaptcha(
            Livewire::test(ListAdministrators::class),
            TestAction::make('resetKataSandi')->table($this->admin),
            jawabanBenar: false,
        )
            ->assertNotified('Gagal!');

        $this->assertTrue(Hash::check('rahasia-lama', $this->admin->refresh()->password));
    }

    public function test_reset_kata_sandi_tidak_tersedia_untuk_akun_sendiri(): void
    {
        Livewire::test(ListAdministrators::class)
            ->searchTable('superadmin')
            ->assertActionHidden(TestAction::make('resetKataSandi')->table($this->superAdmin));
    }

    /**
     * Aksi akun mengikuti permission resource tempatnya dipasang, sehingga juga
     * berlaku pada menu persona lain seperti Dosen.
     */
    public function test_aksi_akun_mengikuti_permission_masing_masing(): void
    {
        $dosen = $this->akunDenganRole('dosensatu', EnumRole::Dosen);

        foreach (['update_username', 'reset_password'] as $ability) {
            Permission::findOrCreate(DosenResource::getPermissionName($ability), 'web');
        }

        $operator = User::factory()->create(['username' => 'operator']);
        $operator->givePermissionTo(Permission::findOrCreate(DosenResource::getPermissionName('view_any'), 'web'));
        $this->actingAs($operator);

        Livewire::test(ListDosens::class)
            ->assertActionHidden(TestAction::make('ubahUsername')->table($dosen))
            ->assertActionHidden(TestAction::make('resetKataSandi')->table($dosen));

        $operator->givePermissionTo(DosenResource::getPermissionName('update_username'));

        Livewire::test(ListDosens::class)
            ->assertActionVisible(TestAction::make('ubahUsername')->table($dosen))
            ->assertActionHidden(TestAction::make('resetKataSandi')->table($dosen));

        $operator->givePermissionTo(DosenResource::getPermissionName('reset_password'));

        Livewire::test(ListDosens::class)
            ->assertActionVisible(TestAction::make('resetKataSandi')->table($dosen));
    }
}
