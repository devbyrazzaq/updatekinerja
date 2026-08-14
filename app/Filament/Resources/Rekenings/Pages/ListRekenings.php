<?php

namespace App\Filament\Resources\Rekenings\Pages;

use App\Exports\RekeningsExport;
use App\Filament\Actions\AuthorizedCreateAction;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\ExcelImportAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Resources\Rekenings\RekeningResource;
use App\Imports\RekeningsImport;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListRekenings extends ListRecords
{
    protected static string $resource = RekeningResource::class;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Data C.O.A';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Berikut adalah daftar C.O.A (kode akun) yang tersedia dalam sistem.';
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            ExcelImportAction::make()->importer(RekeningsImport::class)->permission('import_rekening'),
            ExcelExportAction::make()->exporter(RekeningsExport::class)->permission('export_rekening'),
            PdfReportAction::make()->reporter(RekeningsExport::class)->permission('report_rekening'),
            AuthorizedCreateAction::make()->label('Tambah C.O.A'),
        ];
    }
}
