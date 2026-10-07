<?php

namespace App\Filament\Resources\UnitKerjas\Pages;

use App\Exports\UnitKerjasExport;
use App\Filament\Actions\AuthorizedCreateAction;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\ExcelImportAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Resources\UnitKerjas\UnitKerjaResource;
use App\Imports\UnitKerjasImport;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ListUnitKerjas extends ListRecords
{
    protected static string $resource = UnitKerjaResource::class;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Data Unit Kerja';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Berikut adalah daftar Unit Kerja yang tersedia dalam sistem.';
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ExcelImportAction::make()->importer(UnitKerjasImport::class)->permission('import_unit_kerja'),
                ExcelExportAction::make()->exporter(UnitKerjasExport::class)->permission('export_unit_kerja'),
                PdfReportAction::make()->reporter(UnitKerjasExport::class)->permission('report_unit_kerja'),
            ])
                ->label('Impor & Ekspor')
                ->icon(Heroicon::OutlinedArrowsUpDown)
                ->color('gray')
                ->button(),
            AuthorizedCreateAction::make()->label('Tambah Unit Kerja'),
        ];
    }
}
