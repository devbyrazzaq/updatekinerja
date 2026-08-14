<?php

namespace App\Filament\Resources\DaftarProgramKerjas\Pages;

use App\Filament\Resources\Concerns\HasUnitKerjaPageFilter;
use App\Filament\Resources\DaftarProgramKerjas\DaftarProgramKerjaResource;
use App\Filament\Resources\DaftarProgramKerjas\Widgets\DaftarProgramKerjaOverview;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;

class ListDaftarProgramKerjas extends ListRecords
{
    use HasUnitKerjaPageFilter;

    protected static string $resource = DaftarProgramKerjaResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->unitKerjaId ??= $this->unitKerjaBawaan();
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Daftar Program Kerja';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Program kerja yang ditawarkan untuk tahun kerja aktif. Ajukan untuk mengusulkan pelaksanaan.';
    }

    /**
     * Urutan tampilan: penyaring di paling atas, ringkasan yang mengikuti penyaring,
     * lalu tabel. Ringkasan sengaja tidak dipasang sebagai header widget agar tidak
     * mendahului penyaring yang menentukan angkanya.
     */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->penyaringUnitKerjaSection(),
                ...$this->getWidgetsSchemaComponents($this->ringkasanWidgets()),
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    /**
     * Ringkasan yang dirender di antara penyaring dan tabel.
     *
     * @return array<int, class-string>
     */
    protected function ringkasanWidgets(): array
    {
        return [
            DaftarProgramKerjaOverview::class,
        ];
    }
}
