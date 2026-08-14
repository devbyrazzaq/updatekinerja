<?php

namespace App\Filament\Resources\Periodes\Pages;

use App\Exports\PeriodesExport;
use App\Filament\Actions\AuthorizedCreateAction;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\ExcelImportAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Resources\Periodes\PeriodeResource;
use App\Imports\PeriodesImport;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListPeriodes extends ListRecords
{
    protected static string $resource = PeriodeResource::class;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Data Periode';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Berikut adalah daftar Periode yang tersedia dalam sistem.';
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            ExcelImportAction::make()->importer(PeriodesImport::class)->permission('import_periode'),
            ExcelExportAction::make()->exporter(PeriodesExport::class)->permission('export_periode'),
            PdfReportAction::make()->reporter(PeriodesExport::class)->permission('report_periode'),
            AuthorizedCreateAction::make()->label('Tambah Periode'),
        ];
    }
}
