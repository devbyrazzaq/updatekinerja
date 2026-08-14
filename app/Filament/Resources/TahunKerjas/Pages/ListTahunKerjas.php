<?php

namespace App\Filament\Resources\TahunKerjas\Pages;

use App\Exports\TahunKerjasExport;
use App\Filament\Actions\AuthorizedCreateAction;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\ExcelImportAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Resources\TahunKerjas\TahunKerjaResource;
use App\Imports\TahunKerjasImport;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListTahunKerjas extends ListRecords
{
    protected static string $resource = TahunKerjaResource::class;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Data Tahun Kerja';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Berikut adalah daftar Tahun Kerja yang tersedia dalam sistem.';
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            ExcelImportAction::make()->importer(TahunKerjasImport::class)->permission('import_tahun_kerja'),
            ExcelExportAction::make()->exporter(TahunKerjasExport::class)->permission('export_tahun_kerja'),
            PdfReportAction::make()->reporter(TahunKerjasExport::class)->permission('report_tahun_kerja'),
            AuthorizedCreateAction::make()->label('Tambah Tahun Kerja'),
        ];
    }
}
