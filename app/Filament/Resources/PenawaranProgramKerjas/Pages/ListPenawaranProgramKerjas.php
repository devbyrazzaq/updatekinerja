<?php

namespace App\Filament\Resources\PenawaranProgramKerjas\Pages;

use App\Enums\EnumModeGenerate;
use App\Exports\PenawaranProgramKerjasExport;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Pages\PengaturanProgramKerja;
use App\Filament\Resources\PenawaranProgramKerjas\PenawaranProgramKerjaResource;
use App\Services\GeneratePenawaranFromAcuan;
use App\Services\KonteksProgramKerja;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use RuntimeException;

class ListPenawaranProgramKerjas extends ListRecords
{
    protected static string $resource = PenawaranProgramKerjaResource::class;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Data Penawaran Program Kerja';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $konteks = KonteksProgramKerja::label();

        if ($konteks === null) {
            return 'Konteks program kerja belum ditetapkan. Buka Pengaturan Sistem → Pengaturan Program Kerja untuk memilih kelompok acuan & tahun kerja, lalu bentuk penawarannya.';
        }

        return "Penawaran program kerja pada konteks aktif: {$konteks}. Data ini dibentuk dari acuan program kerja, bukan diinput manual.";
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sinkronDariAcuan')
                ->label('Sinkron Ulang dari Acuan')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => PenawaranProgramKerjaResource::currentUserCanSinkron())
                ->disabled(fn (): bool => ! KonteksProgramKerja::siap())
                ->requiresConfirmation()
                ->modalHeading('Sinkron Ulang Penawaran dari Acuan')
                ->modalDescription(fn (): string => KonteksProgramKerja::siap()
                    ? 'Menyelaraskan penawaran pada konteks aktif ('.KonteksProgramKerja::label().') dengan acuan program kerja terkini, sekaligus menambahkan penawaran untuk acuan baru. Perubahan manual pada penawaran akan tertimpa; pengajuan yang sudah berjalan tetap aman.'
                    : 'Konteks program kerja belum ditetapkan.')
                ->modalSubmitActionLabel('Ya, Sinkron Ulang')
                ->action(function (Action $action): void {
                    $kelompokAcuan = KonteksProgramKerja::kelompokAcuan();
                    $tahunKerja = KonteksProgramKerja::tahunBerjalan();

                    if ($kelompokAcuan === null || $tahunKerja === null) {
                        Notification::make()
                            ->title('Sinkron dibatalkan')
                            ->body('Tetapkan kelompok acuan & tahun kerja aktif lebih dulu di halaman Pengaturan Program Kerja.')
                            ->danger()
                            ->send();

                        $action->halt();
                    }

                    try {
                        $hasil = app(GeneratePenawaranFromAcuan::class)
                            ->handle($kelompokAcuan, $tahunKerja, EnumModeGenerate::Sinkron);
                    } catch (RuntimeException $exception) {
                        Notification::make()
                            ->title('Sinkron gagal')
                            ->body($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        $action->halt();
                    }

                    Notification::make()
                        ->title('Sinkron penawaran selesai')
                        ->body("{$hasil['created']} dibuat, {$hasil['updated']} diperbarui, {$hasil['skipped']} dilewati.")
                        ->success()
                        ->send();
                }),
            Action::make('aturKonteks')
                ->label('Atur Kelompok Acuan & Tahun Kerja')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->visible(fn (): bool => PengaturanProgramKerja::canAccess())
                ->url(fn (): string => PengaturanProgramKerja::getUrl()),
            ExcelExportAction::make()->exporter(PenawaranProgramKerjasExport::class)->permission('export_penawaran_program_kerja'),
            PdfReportAction::make()->reporter(PenawaranProgramKerjasExport::class)->permission('report_penawaran_program_kerja'),
        ];
    }
}
