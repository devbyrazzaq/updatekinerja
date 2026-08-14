<?php

namespace App\Filament\Resources\Pemasukans\Pages;

use App\Exports\PemasukansExport;
use App\Filament\Actions\AuthorizedCreateAction;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Resources\Concerns\HasUnitKerjaPageFilter;
use App\Filament\Resources\Pemasukans\PemasukanResource;
use App\Filament\Resources\Pemasukans\Widgets\PemasukanOverview;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;

class ListPemasukans extends ListRecords
{
    use HasUnitKerjaPageFilter;

    protected static string $resource = PemasukanResource::class;

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
        return 'Data Pemasukan Unit';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Catatan pemasukan/pendapatan dari masing-masing unit kerja beserta program kerja sumbernya.';
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            ExcelExportAction::make()->exporter(PemasukansExport::class)->permission('export_pemasukan'),
            PdfReportAction::make()->reporter(PemasukansExport::class)->permission('report_pemasukan'),
            AuthorizedCreateAction::make()->label('Tambah Pemasukan'),
        ];
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
            PemasukanOverview::class,
        ];
    }
}
