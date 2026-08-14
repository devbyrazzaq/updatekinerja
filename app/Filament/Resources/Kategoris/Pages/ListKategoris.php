<?php

namespace App\Filament\Resources\Kategoris\Pages;

use App\Exports\KategorisExport;
use App\Filament\Actions\AuthorizedCreateAction;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\ExcelImportAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Resources\Kategoris\KategoriResource;
use App\Imports\KategorisImport;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListKategoris extends ListRecords
{
    protected static string $resource = KategoriResource::class;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Data Kategori';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Berikut adalah daftar Kategori yang tersedia dalam sistem.';
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            ExcelImportAction::make()->importer(KategorisImport::class)->permission('import_kategori'),
            ExcelExportAction::make()->exporter(KategorisExport::class)->permission('export_kategori'),
            PdfReportAction::make()->reporter(KategorisExport::class)->permission('report_kategori'),
            AuthorizedCreateAction::make()->label('Tambah Kategori'),
        ];
    }
}
