<?php

namespace App\Filament\Resources\Users\Pages;

use App\Exports\UsersExport;
use App\Filament\Actions\AuthorizedCreateAction;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\ExcelImportAction;
use App\Filament\Actions\PdfReportAction;
use App\Imports\UsersImport;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

abstract class ListUsers extends ListRecords
{
    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return static::getResource()::getPluralModelLabel();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Berikut adalah daftar '.static::getResource()::getPluralModelLabel().' yang tersedia dalam sistem.';
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ExcelImportAction::make()->importer(UsersImport::class)->permission(static::getResource()::getPermissionName('import')),
                ExcelExportAction::make()->exporter(UsersExport::class)->permission(static::getResource()::getPermissionName('export')),
                PdfReportAction::make()->reporter(UsersExport::class)->permission(static::getResource()::getPermissionName('report')),
            ])
                ->label('Impor & Ekspor')
                ->icon(Heroicon::OutlinedArrowsUpDown)
                ->color('gray')
                ->button(),
            AuthorizedCreateAction::make()->label('Tambah '.static::getResource()::getModelLabel()),
        ];
    }
}
