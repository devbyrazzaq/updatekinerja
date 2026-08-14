<?php

namespace App\Filament\Resources\Users\Pages;

use App\Exports\UsersExport;
use App\Filament\Actions\AuthorizedCreateAction;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\ExcelImportAction;
use App\Filament\Actions\PdfReportAction;
use App\Imports\UsersImport;
use Filament\Resources\Pages\ListRecords;
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
            ExcelImportAction::make()->importer(UsersImport::class)->permission(static::getResource()::getPermissionName('import')),
            ExcelExportAction::make()->exporter(UsersExport::class)->permission(static::getResource()::getPermissionName('export')),
            PdfReportAction::make()->reporter(UsersExport::class)->permission(static::getResource()::getPermissionName('report')),
            AuthorizedCreateAction::make()->label('Tambah '.static::getResource()::getModelLabel()),
        ];
    }
}
