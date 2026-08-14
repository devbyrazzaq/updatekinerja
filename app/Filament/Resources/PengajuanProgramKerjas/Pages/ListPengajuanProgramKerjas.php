<?php

namespace App\Filament\Resources\PengajuanProgramKerjas\Pages;

use App\Exports\PengajuanProgramKerjasExport;
use App\Filament\Actions\AuthorizedCreateAction;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Resources\PengajuanProgramKerjas\PengajuanProgramKerjaResource;
use App\Filament\Resources\PengajuanProgramKerjas\Widgets\PengajuanProgramKerjaOverview;
use App\Models\UnitKerja;
use App\Services\PermissionRegistrar;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;

class ListPengajuanProgramKerjas extends ListRecords
{
    protected static string $resource = PengajuanProgramKerjaResource::class;

    /**
     * Unit kerja yang dipilih untuk penyaringan tampilan. Null berarti seluruh unit
     * yang boleh diakses ditampilkan.
     */
    public ?int $unitKerjaId = null;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Data Pengajuan Program Kerja';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Berikut adalah daftar Pengajuan Program Kerja yang tersedia dalam sistem.';
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            ExcelExportAction::make()
                ->exporter(PengajuanProgramKerjasExport::class)
                ->permission(static::getResource()::getPermissionName('export')),
            PdfReportAction::make()
                ->reporter(PengajuanProgramKerjasExport::class)
                ->permission(static::getResource()::getPermissionName('report')),
            AuthorizedCreateAction::make()->label('Tambah Pengajuan Program Kerja'),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tampilkan Data')
                    ->description('Pilih unit kerja untuk menyaring data yang tampil. Kosongkan untuk menampilkan seluruh unit.')
                    ->icon(Heroicon::OutlinedFunnel)
                    ->collapsible()
                    ->schema([
                        Select::make('unitKerjaId')
                            ->label('Unit Kerja')
                            ->placeholder('Semua Unit Kerja')
                            ->options($this->unitKerjaOptions())
                            ->searchable()
                            ->native(false)
                            ->live(),
                    ]),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    /**
     * @return array<int, class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            PengajuanProgramKerjaOverview::class,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            'unitKerjaId' => $this->unitKerjaId,
        ];
    }

    /**
     * Unit kerja yang boleh dipilih pengguna, mengikuti scope data yang dimiliki.
     *
     * @return array<int, string>
     */
    protected function unitKerjaOptions(): array
    {
        $query = UnitKerja::query()->where('is_active', true)->orderBy('name');

        $user = auth()->user();

        if ($user !== null && ! $user->isPrivileged()) {
            $query->whereIn('id', PermissionRegistrar::permittedUnitIds($user)->all());
        }

        return $query->pluck('name', 'id')->all();
    }
}
