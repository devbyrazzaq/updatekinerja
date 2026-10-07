<?php

namespace App\Filament\Resources\PaguAnggarans\Pages;

use App\Exports\PaguAnggaransExport;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\ExcelImportAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Resources\PaguAnggarans\PaguAnggaranResource;
use App\Filament\Resources\PaguAnggarans\Widgets\PaguAnggaranOverview;
use App\Imports\PaguAnggaransImport;
use App\Models\PaguAnggaran;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ListPaguAnggarans extends ListRecords
{
    protected static string $resource = PaguAnggaranResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->ensurePaguAnggaransForActiveTahunKerja();
    }

    /**
     * Pastikan setiap unit kerja memiliki baris pagu anggaran pada tahun kerja aktif.
     * Unit yang belum memiliki pagu dibuatkan dengan nominal 0 sehingga seluruh unit
     * tetap muncul dan tinggal diubah nominalnya.
     */
    protected function ensurePaguAnggaransForActiveTahunKerja(): void
    {
        $tahunKerja = TahunKerja::berjalan();

        if (! $tahunKerja instanceof TahunKerja) {
            return;
        }

        $existingUnitKerjaIds = PaguAnggaran::query()
            ->where('tahun_kerja_id', $tahunKerja->id)
            ->pluck('unit_kerja_id');

        UnitKerja::query()
            ->whereNotIn('id', $existingUnitKerjaIds)
            ->get()
            ->each(function (UnitKerja $unitKerja) use ($tahunKerja): void {
                PaguAnggaran::create([
                    'tahun_kerja_id' => $tahunKerja->id,
                    'unit_kerja_id' => $unitKerja->id,
                    'amount' => 0,
                ]);
            });
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        $tahunKerja = TahunKerja::berjalan();

        return $tahunKerja instanceof TahunKerja
            ? "Data Pagu Anggaran {$tahunKerja->name}"
            : 'Data Pagu Anggaran';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Berikut adalah daftar Pagu Anggaran yang tersedia dalam sistem.';
    }

    /**
     * @return array<int, class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            PaguAnggaranOverview::class,
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ExcelImportAction::make()->importer(PaguAnggaransImport::class)->permission('import_pagu_anggaran'),
                ExcelExportAction::make()->exporter(PaguAnggaransExport::class)->permission('export_pagu_anggaran'),
                PdfReportAction::make()->reporter(PaguAnggaransExport::class)->permission('report_pagu_anggaran'),
            ])
                ->label('Impor & Ekspor')
                ->icon(Heroicon::OutlinedArrowsUpDown)
                ->color('gray')
                ->button(),
        ];
    }
}
