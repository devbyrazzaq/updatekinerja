<?php

namespace Tests\Feature;

use App\Filament\Resources\Banks\Pages\CreateBank;
use App\Filament\Resources\Banks\Pages\ListBanks;
use App\Filament\Resources\RekeningBanks\Pages\CreateRekeningBank;
use App\Filament\Resources\RekeningBanks\Pages\EditRekeningBank;
use App\Filament\Resources\RekeningBanks\Pages\ListRekeningBanks;
use App\Models\Bank;
use App\Models\RekeningBank;
use App\Models\UnitKerja;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RekeningBankTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Menu ini dipegang pengguna berakses penuh yang boleh mendaftarkan rekening
        // unit mana pun, termasuk rekening umum. Perilaku pengguna yang datanya
        // dibatasi unit kerja diuji terpisah pada PimpinanUnitTest.
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('bypass_data_scope', 'web'));

        $this->actingAs($user);
    }

    public function test_list_pages_render(): void
    {
        Livewire::test(ListBanks::class)->assertOk();
        Livewire::test(ListRekeningBanks::class)->assertOk();
    }

    public function test_bank_dibuat_dengan_nama_dan_kode(): void
    {
        Livewire::test(CreateBank::class)
            ->fillForm(['name' => 'Bank Syariah Indonesia', 'code' => '451'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Bank::class, ['name' => 'Bank Syariah Indonesia', 'code' => '451']);
    }

    public function test_nama_bank_wajib_dan_tidak_boleh_kembar(): void
    {
        Bank::factory()->create(['name' => 'Bank Mandiri']);

        Livewire::test(CreateBank::class)
            ->fillForm(['name' => null])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);

        Livewire::test(CreateBank::class)
            ->fillForm(['name' => 'Bank Mandiri'])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique']);
    }

    public function test_rekening_bank_dibuat_dengan_bank_nomor_dan_atas_nama(): void
    {
        $bank = Bank::factory()->create();
        $unit = UnitKerja::create(['name' => 'Fakultas Teknik']);

        Livewire::test(CreateRekeningBank::class)
            ->fillForm([
                'bank_id' => $bank->id,
                'unit_kerja_id' => $unit->id,
                'nomor_rekening' => '1234567890',
                'atas_nama' => 'Fakultas Teknik',
                'is_utama' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(RekeningBank::class, [
            'bank_id' => $bank->id,
            'unit_kerja_id' => $unit->id,
            'nomor_rekening' => '1234567890',
            'atas_nama' => 'Fakultas Teknik',
            'is_utama' => true,
        ]);
    }

    public function test_bank_nomor_dan_atas_nama_wajib_diisi(): void
    {
        Livewire::test(CreateRekeningBank::class)
            ->fillForm(['bank_id' => null, 'nomor_rekening' => null, 'atas_nama' => null])
            ->call('create')
            ->assertHasFormErrors([
                'bank_id' => 'required',
                'nomor_rekening' => 'required',
                'atas_nama' => 'required',
            ]);
    }

    /**
     * Menandai satu rekening sebagai utama melepas penanda pada rekening lain milik
     * unit kerja yang sama, namun tidak mengubah rekening unit kerja lain.
     */
    public function test_penanda_utama_hanya_untuk_satu_rekening_per_unit_kerja(): void
    {
        $unit = UnitKerja::create(['name' => 'Fakultas Teknik']);
        $unitLain = UnitKerja::create(['name' => 'Fakultas Ekonomi']);

        $lama = RekeningBank::factory()->utama()->create(['unit_kerja_id' => $unit->id]);
        $unitLainUtama = RekeningBank::factory()->utama()->create(['unit_kerja_id' => $unitLain->id]);
        $baru = RekeningBank::factory()->create(['unit_kerja_id' => $unit->id]);

        Livewire::test(EditRekeningBank::class, ['record' => $baru->id])
            ->fillForm(['is_utama' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($baru->refresh()->is_utama);
        $this->assertFalse($lama->refresh()->is_utama);
        $this->assertTrue($unitLainUtama->refresh()->is_utama);
    }

    /**
     * Bank yang belum terdaftar dapat ditambahkan langsung dari select nama bank pada
     * form rekening, tanpa berpindah ke menu Bank.
     */
    public function test_bank_baru_dapat_ditambahkan_dari_form_rekening(): void
    {
        Livewire::test(CreateRekeningBank::class)
            ->callAction(TestAction::make('createOption')->schemaComponent('bank_id'), [
                'name' => 'Bank Nagari',
                'code' => '118',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas(Bank::class, ['name' => 'Bank Nagari', 'code' => '118', 'is_active' => true]);

        $bank = Bank::query()->where('name', 'Bank Nagari')->sole();

        Livewire::test(CreateRekeningBank::class)
            ->fillForm([
                'bank_id' => $bank->id,
                'nomor_rekening' => '5550001111',
                'atas_nama' => 'Universitas',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(RekeningBank::class, ['bank_id' => $bank->id, 'nomor_rekening' => '5550001111']);
    }

    /**
     * Keunikan nama bank tetap diperiksa terhadap tabel banks meski modal "tambah bank"
     * dibuka dari form rekening yang model-nya bukan Bank.
     */
    public function test_nama_bank_kembar_ditolak_pada_modal_tambah_bank(): void
    {
        Bank::factory()->create(['name' => 'Bank Mandiri']);

        Livewire::test(CreateRekeningBank::class)
            ->callAction(TestAction::make('createOption')->schemaComponent('bank_id'), [
                'name' => 'Bank Mandiri',
            ])
            ->assertHasActionErrors(['name' => 'unique']);

        $this->assertSame(1, Bank::query()->where('name', 'Bank Mandiri')->count());
    }

    public function test_label_rekening_menggabungkan_bank_nomor_dan_atas_nama(): void
    {
        $rekening = RekeningBank::factory()->create([
            'bank_id' => Bank::factory()->create(['name' => 'Bank Syariah Indonesia'])->id,
            'nomor_rekening' => '1234567890',
            'atas_nama' => 'Fakultas Teknik',
        ]);

        $this->assertSame(
            'Bank Syariah Indonesia 1234567890 — a.n. Fakultas Teknik',
            $rekening->label(),
        );
    }
}
