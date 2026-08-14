<?php

namespace Tests\Feature;

use App\Enums\EnumRole;
use App\Filament\Resources\Dosens\DosenResource;
use App\Filament\Resources\Dosens\Pages\CreateDosen;
use App\Filament\Resources\Dosens\Pages\EditDosen;
use App\Filament\Resources\Dosens\Pages\ListDosens;
use App\Filament\Resources\TenagaPendidiks\Pages\ListTenagaPendidiks;
use App\Filament\Resources\TenagaPendidiks\TenagaPendidikResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Database\Seeders\DosenSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use ReflectionClass;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Grup "Pengguna" hanya berisi menu Dosen dan Tenaga Pendidik: keduanya berbagi satu
 * tabel `users` lewat kerangka bersama UserResource, disaring role utamanya.
 */
class PersonaPenggunaTest extends TestCase
{
    use RefreshDatabase;

    private User $dosen;

    private User $tendik;

    private User $tanpaPersona;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (EnumRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        $this->dosen = $this->userDenganRole('dosensatu', EnumRole::Dosen);
        $this->tendik = $this->userDenganRole('tendiksatu', EnumRole::TenagaPendidik);
        $this->tanpaPersona = User::factory()->create(['username' => 'adminsatu']);

        $this->actingAs($this->tanpaPersona);
    }

    private function userDenganRole(string $username, EnumRole $role): User
    {
        $user = User::factory()->create(['username' => $username]);
        $user->assignRole($role->value);

        return $user;
    }

    public function test_kedua_menu_berada_di_grup_pengguna(): void
    {
        $this->assertSame('Pengguna', DosenResource::getNavigationGroup());
        $this->assertSame('Pengguna', TenagaPendidikResource::getNavigationGroup());
    }

    /**
     * Kerangka bersamanya abstrak sehingga tidak ikut terdaftar sebagai menu
     * generik "Semua Pengguna" di samping kedua menu persona.
     */
    public function test_grup_pengguna_hanya_memuat_menu_persona(): void
    {
        $this->assertTrue((new ReflectionClass(UserResource::class))->isAbstract());

        $menuGrupPengguna = collect(Filament::getPanel('app')->getResources())
            ->filter(fn (string $resource): bool => $resource::getNavigationGroup() === 'Pengguna')
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing(
            [DosenResource::class, TenagaPendidikResource::class],
            $menuGrupPengguna,
        );
    }

    public function test_menu_dosen_hanya_memuat_akun_berrole_dosen(): void
    {
        Livewire::test(ListDosens::class)
            ->assertCanSeeTableRecords([$this->dosen])
            ->assertCanNotSeeTableRecords([$this->tendik, $this->tanpaPersona]);
    }

    public function test_menu_tenaga_pendidik_hanya_memuat_akun_berrole_tenaga_pendidik(): void
    {
        Livewire::test(ListTenagaPendidiks::class)
            ->assertCanSeeTableRecords([$this->tendik])
            ->assertCanNotSeeTableRecords([$this->dosen, $this->tanpaPersona]);
    }

    public function test_akun_tanpa_persona_tidak_muncul_di_menu_mana_pun(): void
    {
        Livewire::test(ListDosens::class)
            ->assertCanNotSeeTableRecords([$this->tanpaPersona]);

        Livewire::test(ListTenagaPendidiks::class)
            ->assertCanNotSeeTableRecords([$this->tanpaPersona]);
    }

    public function test_membuat_akun_dari_menu_dosen_melekatkan_role_dosen(): void
    {
        Livewire::test(CreateDosen::class)
            ->fillForm([
                'name' => 'Dosen Baru',
                'username' => 'dosenbaru',
                'email' => 'dosenbaru@umla.ac.id',
                'birth_date' => '1990-05-17',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $baru = User::where('username', 'dosenbaru')->firstOrFail();

        $this->assertTrue($baru->hasRole(EnumRole::Dosen->value));
        $this->assertTrue(Hash::check('dosenbaru17', $baru->password));
    }

    public function test_menyimpan_role_tambahan_tidak_mencabut_role_utama(): void
    {
        Livewire::test(EditDosen::class, ['record' => $this->dosen->username])
            ->fillForm(['roles' => [Role::findByName(EnumRole::PimpinanUnit->value)->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->dosen->refresh();

        $this->assertTrue($this->dosen->hasRole(EnumRole::Dosen->value));
        $this->assertTrue($this->dosen->hasRole(EnumRole::PimpinanUnit->value));
    }

    public function test_seeder_memindahkan_akun_dosen_dari_lamadu(): void
    {
        $this->seed(DosenSeeder::class);

        $this->assertSame(181, User::role(EnumRole::Dosen->value)->where('username', '!=', 'dosensatu')->count());

        // Hash kata sandi dibawa apa adanya, bukan di-hash ulang saat disimpan.
        $contoh = User::where('username', '0728089201')->firstOrFail();
        $this->assertSame(
            '$2y$12$hTdsI4f5eAt.ldtkgDqideelssKUmHyEKNR1pg9x7KseheWUZeaqa',
            $contoh->password,
        );
    }

    public function test_seeder_dosen_idempoten(): void
    {
        $this->seed(DosenSeeder::class);
        $jumlah = User::count();

        $this->seed(DosenSeeder::class);

        $this->assertSame($jumlah, User::count());
    }
}
