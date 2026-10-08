<?php

namespace App\Filament\Resources\RealisasiProgramKerjas\Pages;

use App\Exports\RealisasiProgramKerjasExport;
use App\Filament\Actions\AuthorizedCreateAction;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Resources\RealisasiProgramKerjas\RealisasiProgramKerjaResource;
use App\Filament\Resources\RealisasiProgramKerjas\Widgets\PenggunaanAnggaranChart;
use App\Filament\Resources\RealisasiProgramKerjas\Widgets\RealisasiProgramKerjaOverview;
use App\Models\UnitKerja;
use App\Services\PermissionRegistrar;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;

class ListRealisasiProgramKerjas extends ListRecords
{
    protected static string $resource = RealisasiProgramKerjaResource::class;

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
        return 'Data Realisasi Program Kerja';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Berikut adalah daftar Realisasi Program Kerja yang tersedia dalam sistem.';
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ExcelExportAction::make()->exporter(RealisasiProgramKerjasExport::class)->permission('export_realisasi_program_kerja'),
                PdfReportAction::make()->reporter(RealisasiProgramKerjasExport::class)->permission('report_realisasi_program_kerja'),
            ])
                ->label('Ekspor')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->button(),
            AuthorizedCreateAction::make()->label('Tambah Realisasi Program Kerja'),
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
            RealisasiProgramKerjaOverview::class,
            PenggunaanAnggaranChart::class,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            'unitKerjaId' => $this->unitKerjaId,
            'permissionLingkup' => static::getResource()::getPermissionName('view_any'),
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

        if ($user !== null && ! $user->canViewAllUnitData(static::getResource()::getPermissionName('view_any'))) {
            $query->whereIn('id', PermissionRegistrar::permittedUnitIds($user)->all());
        }

        return $query->pluck('name', 'id')->all();
    }
}
