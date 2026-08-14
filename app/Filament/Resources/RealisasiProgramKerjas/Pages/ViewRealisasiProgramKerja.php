<?php

namespace App\Filament\Resources\RealisasiProgramKerjas\Pages;

use App\Filament\Actions\AjukanRealisasiAction;
use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Actions\KirimLaporanRealisasiAction;
use App\Filament\Resources\RealisasiProgramKerjas\RealisasiProgramKerjaResource;
use App\Models\RealisasiProgramKerja;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewRealisasiProgramKerja extends ViewRecord
{
    protected static string $resource = RealisasiProgramKerjaResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Detail Realisasi Program Kerja';
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            RealisasiProgramKerjaResource::getUrl() => 'Data Realisasi Program Kerja',
            '' => 'Detail',
        ];
    }

    /**
     * Realisasi selalu diajukan ulang lewat aksi perbaikan, bukan tombol "ajukan
     * kembali" tersendiri: revisi pada tahap proposal diperbaiki lewat
     * {@see AjukanRealisasiAction}, revisi pada tahap laporan lewat
     * {@see KirimLaporanRealisasiAction}. Keduanya menampilkan catatan revisi
     * verifikator di dalam modalnya. Realisasi yang sudah diajukan tidak dapat
     * dibatalkan.
     *
     * @return array<int, AjukanRealisasiAction|AuthorizedEditAction|KirimLaporanRealisasiAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            AjukanRealisasiAction::make(),
            KirimLaporanRealisasiAction::make()
                ->label(fn (RealisasiProgramKerja $record): string => $record->adalahRevisiLaporan()
                    ? 'Perbaiki Laporan'
                    : 'Kirim Laporan Realisasi'),
            AuthorizedEditAction::make()
                ->label('Masuk ke Edit Mode')
                ->icon('heroicon-o-pencil-square'),
        ];
    }
}
