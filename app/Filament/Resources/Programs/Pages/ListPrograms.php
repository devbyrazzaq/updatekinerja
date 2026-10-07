<?php

namespace App\Filament\Resources\Programs\Pages;

use App\Exports\ProgramsExport;
use App\Filament\Actions\AuthorizedCreateAction;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\ExcelImportAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Resources\Programs\ProgramResource;
use App\Imports\ProgramsImport;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ListPrograms extends ListRecords
{
    protected static string $resource = ProgramResource::class;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Data Program Induk';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Berikut adalah daftar Program Induk yang tersedia dalam sistem.';
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ExcelImportAction::make()->importer(ProgramsImport::class)->permission('import_program'),
                ExcelExportAction::make()->exporter(ProgramsExport::class)->permission('export_program'),
                PdfReportAction::make()->reporter(ProgramsExport::class)->permission('report_program'),
            ])
                ->label('Impor & Ekspor')
                ->icon(Heroicon::OutlinedArrowsUpDown)
                ->color('gray')
                ->button(),
            AuthorizedCreateAction::make()->label('Tambah Program Induk'),
        ];
    }
}
