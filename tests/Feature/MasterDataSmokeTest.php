<?php

namespace Tests\Feature;

use App\Filament\Resources\Bidangs\Pages\CreateBidang;
use App\Filament\Resources\Bidangs\Pages\ListBidangs;
use App\Filament\Resources\Kategoris\Pages\CreateKategori;
use App\Filament\Resources\Kategoris\Pages\ListKategoris;
use App\Filament\Resources\Periodes\Pages\CreatePeriode;
use App\Filament\Resources\Periodes\Pages\ListPeriodes;
use App\Filament\Resources\Programs\Pages\CreateProgram;
use App\Filament\Resources\Programs\Pages\ListPrograms;
use App\Filament\Resources\TahunKerjas\Pages\CreateTahunKerja;
use App\Filament\Resources\TahunKerjas\Pages\ListTahunKerjas;
use App\Filament\Resources\UnitKerjas\Pages\CreateUnitKerja;
use App\Filament\Resources\UnitKerjas\Pages\ListUnitKerjas;
use App\Models\Kategori;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MasterDataSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_list_pages_render(): void
    {
        Livewire::test(ListPeriodes::class)->assertOk();
        Livewire::test(ListTahunKerjas::class)->assertOk();
        Livewire::test(ListKategoris::class)->assertOk();
        Livewire::test(ListBidangs::class)->assertOk();
        Livewire::test(ListUnitKerjas::class)->assertOk();
        Livewire::test(ListPrograms::class)->assertOk();
    }

    public function test_create_pages_render(): void
    {
        Livewire::test(CreatePeriode::class)->assertOk();
        Livewire::test(CreateTahunKerja::class)->assertOk();
        Livewire::test(CreateKategori::class)->assertOk();
        Livewire::test(CreateBidang::class)->assertOk();
        Livewire::test(CreateUnitKerja::class)->assertOk();
        Livewire::test(CreateProgram::class)->assertOk();
    }

    public function test_can_create_kategori(): void
    {
        Livewire::test(CreateKategori::class)
            ->fillForm([
                'code' => 'KAT-1',
                'name' => 'Pendidikan',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Kategori::class, [
            'code' => 'KAT-1',
            'name' => 'Pendidikan',
            'slug' => 'pendidikan',
        ]);
    }

    public function test_activating_periode_deactivates_others(): void
    {
        $first = Periode::create([
            'name' => 'Periode A',
            'start_datetime' => now(),
            'end_datetime' => now()->addYear(),
            'is_active' => true,
        ]);

        $second = Periode::create([
            'name' => 'Periode B',
            'start_datetime' => now(),
            'end_datetime' => now()->addYear(),
            'is_active' => true,
        ]);

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
    }
}
