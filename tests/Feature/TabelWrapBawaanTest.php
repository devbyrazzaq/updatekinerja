<?php

namespace Tests\Feature;

use App\Filament\Resources\AcuanProgramKerjas\Pages\ListAcuanProgramKerjas;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TabelWrapBawaanTest extends TestCase
{
    use RefreshDatabase;

    public function test_kolom_teks_membungkus_secara_bawaan(): void
    {
        $this->assertTrue(TextColumn::make('name')->canWrap());
    }

    public function test_kolom_masih_bisa_keluar_dari_aturan_bawaan(): void
    {
        $this->assertFalse(TextColumn::make('name')->wrap(false)->canWrap());
    }

    public function test_tabel_resource_ikut_membungkus(): void
    {
        $this->actingAs(User::factory()->create());

        $columns = Livewire::test(ListAcuanProgramKerjas::class)
            ->instance()
            ->getTable()
            ->getColumns();

        $textColumns = array_filter($columns, fn ($column): bool => $column instanceof TextColumn);

        $this->assertNotEmpty($textColumns);

        foreach ($textColumns as $name => $column) {
            $this->assertTrue($column->canWrap(), "Kolom \"{$name}\" tidak membungkus.");
        }
    }
}
