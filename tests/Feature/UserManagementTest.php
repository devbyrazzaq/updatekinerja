<?php

namespace Tests\Feature;

use App\Enums\EnumJenisKelamin;
use App\Enums\EnumRole;
use App\Filament\Resources\Dosens\Pages\CreateDosen;
use App\Filament\Resources\Dosens\Pages\ListDosens;
use App\Filament\Resources\Dosens\Pages\ViewDosen;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Menu Dosen melekatkan role utamanya pada tiap akun yang dibuat dari sana.
        Role::findOrCreate(EnumRole::Dosen->value, 'web');

        $this->actingAs(User::factory()->create());
    }

    public function test_list_and_create_pages_render(): void
    {
        Livewire::test(ListDosens::class)->assertOk();
        Livewire::test(CreateDosen::class)->assertOk();
    }

    public function test_create_user_generates_password_from_username_and_birthdate(): void
    {
        Livewire::test(CreateDosen::class)
            ->fillForm([
                'name' => 'Budi Santoso',
                'username' => 'budi',
                'email' => 'budi@example.com',
                'birth_date' => '1990-04-15',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('username', 'budi')->first();
        $this->assertNotNull($user);
        // Password awal = username + dua digit tanggal lahir (15).
        $this->assertTrue(Hash::check('budi15', $user->password));
    }

    public function test_create_user_stores_personal_information(): void
    {
        Livewire::test(CreateDosen::class)
            ->fillForm([
                'front_title' => 'Dr.',
                'name' => 'Rahma Sari',
                'back_title' => 'M.Si.',
                'username' => 'rahma',
                'email' => 'rahma@example.com',
                'phone' => '081234567890',
                'gender' => EnumJenisKelamin::Perempuan->value,
                'birth_date' => '1992-09-03',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('username', 'rahma')->first();
        $this->assertNotNull($user);
        $this->assertSame('Dr. Rahma Sari M.Si.', $user->getFullName());
        $this->assertSame(EnumJenisKelamin::Perempuan, $user->gender);
        $this->assertSame('081234567890', $user->phone);
        $this->assertSame(Carbon::parse('1992-09-03')->age, $user->getAge());
    }

    public function test_view_page_shows_personal_information(): void
    {
        $user = User::factory()->create([
            'front_title' => 'Prof.',
            'name' => 'Andi Wijaya',
            'back_title' => 'Ph.D.',
            'username' => 'andi',
            'gender' => EnumJenisKelamin::LakiLaki,
        ]);
        $user->assignRole(EnumRole::Dosen->value);

        Livewire::test(ViewDosen::class, ['record' => $user->username])
            ->assertOk()
            ->assertSee('Prof. Andi Wijaya Ph.D.')
            ->assertSee('Laki-laki');
    }

    public function test_create_user_assigns_roles(): void
    {
        $role = Role::findOrCreate('Unit Kerja', 'web');

        Livewire::test(CreateDosen::class)
            ->fillForm([
                'name' => 'Siti',
                'username' => 'siti',
                'birth_date' => '1988-01-09',
                'roles' => [$role->id],
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('username', 'siti')->first();
        $this->assertTrue($user->hasRole('Unit Kerja'));
    }

    public function test_login_uses_username(): void
    {
        $user = User::factory()->create([
            'username' => 'loginuser',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $this->assertTrue(Auth::attempt(['username' => 'loginuser', 'password' => 'secret123', 'is_active' => true]));
        $this->assertSame($user->id, Auth::id());
    }
}
