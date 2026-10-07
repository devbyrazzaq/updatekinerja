<?php

namespace App\Filament\Resources\TahunKerjas\Pages;

use App\Exports\TahunKerjasExport;
use App\Filament\Actions\AuthorizedCreateAction;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\ExcelImportAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Resources\TahunKerjas\TahunKerjaResource;
use App\Imports\TahunKerjasImport;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
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
            ActionGroup::make([
                ExcelImportAction::make()->importer(TahunKerjasImport::class)->permission('import_tahun_kerja'),
                ExcelExportAction::make()->exporter(TahunKerjasExport::class)->permission('export_tahun_kerja'),
                PdfReportAction::make()->reporter(TahunKerjasExport::class)->permission('report_tahun_kerja'),
            ])
                ->label('Impor & Ekspor')
                ->icon(Heroicon::OutlinedArrowsUpDown)
                ->color('gray')
                ->button(),
            AuthorizedCreateAction::make()->label('Tambah Tahun Kerja'),
        ];
    }
}
