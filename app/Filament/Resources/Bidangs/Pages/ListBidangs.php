<?php

namespace App\Filament\Resources\Bidangs\Pages;

use App\Exports\BidangsExport;
use App\Filament\Actions\AuthorizedCreateAction;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\ExcelImportAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Resources\Bidangs\BidangResource;
use App\Imports\BidangsImport;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListBidangs extends ListRecords
{
    protected static string $resource = BidangResource::class;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Data Bidang';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Berikut adalah daftar Bidang yang tersedia dalam sistem.';
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            ExcelImportAction::make()->importer(BidangsImport::class)->permission('import_bidang'),
            ExcelExportAction::make()->exporter(BidangsExport::class)->permission('export_bidang'),
            PdfReportAction::make()->reporter(BidangsExport::class)->permission('report_bidang'),
            AuthorizedCreateAction::make()->label('Tambah Bidang'),
        ];
    }
}
