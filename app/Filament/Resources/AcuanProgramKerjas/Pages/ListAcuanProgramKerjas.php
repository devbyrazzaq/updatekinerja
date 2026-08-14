<?php

namespace App\Filament\Resources\AcuanProgramKerjas\Pages;

use App\Exports\AcuanProgramKerjasExport;
use App\Filament\Actions\AuthorizedCreateAction;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\ExcelImportAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Resources\AcuanProgramKerjas\AcuanProgramKerjaResource;
use App\Imports\AcuanProgramKerjasImport;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListAcuanProgramKerjas extends ListRecords
{
    protected static string $resource = AcuanProgramKerjaResource::class;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Data Acuan Program Kerja';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Berikut adalah daftar Acuan Program Kerja yang tersedia dalam sistem.';
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            ExcelImportAction::make()->importer(AcuanProgramKerjasImport::class)->permission('import_acuan_program_kerja'),
            ExcelExportAction::make()->exporter(AcuanProgramKerjasExport::class)->permission('export_acuan_program_kerja'),
            PdfReportAction::make()->reporter(AcuanProgramKerjasExport::class)->permission('report_acuan_program_kerja'),
            AuthorizedCreateAction::make()->label('Tambah Acuan Program Kerja'),
        ];
    }
}
