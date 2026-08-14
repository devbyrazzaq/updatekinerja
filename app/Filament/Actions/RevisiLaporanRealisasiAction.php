<?php

namespace App\Filament\Actions;

use App\Enums\EnumStatusRealisasi;
use App\Filament\Resources\VerifikasiLaporans\VerifikasiLaporanResource;
use App\Models\RealisasiProgramKerja;
use App\Services\Notifikasi\NotifikasiVerifikasi;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

/**
 * Aksi "Revisi" pada verifikasi laporan realisasi: mengembalikan laporan ke unit
 * kerja untuk diperbaiki dan dikirim ulang, disertai catatan perbaikan (rich editor)
 * yang tersimpan di log, tampil pada Komentar, dan dikirim sebagai notifikasi ke
 * pengaju.
 */
class RevisiLaporanRealisasiAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'revisiLaporan';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Revisi')
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-pencil-square')
            ->modalIconColor('warning')
            ->modalHeading('Minta Revisi Laporan')
            ->modalDescription('Laporan dikembalikan ke unit kerja untuk diperbaiki dan dikirim ulang. Sampaikan bagian mana yang perlu diperbaiki.')
            ->modalSubmitActionLabel('Kirim Revisi')
            ->modalWidth(Width::Large)
            ->visible(fn (RealisasiProgramKerja $record): bool => VerifikasiLaporanResource::currentUserCanVerify()
                && VerifikasiLaporanResource::isPendingAtStage($record))
            ->schema([
                RichEditor::make('catatan')
                    ->label('Catatan Revisi')
                    ->required(),
            ])
            ->action(function (array $data, RealisasiProgramKerja $record): void {
                $record->update([
                    'status' => EnumStatusRealisasi::Revisi,
                    'catatan_verifikasi' => $data['catatan'],
                    'verifikator_laporan_id' => auth()->id(),
                ]);

                $record->catatLog(EnumStatusRealisasi::Revisi, auth()->id(), $data['catatan']);

                app(NotifikasiVerifikasi::class)->realisasiRevisi($record, $data['catatan']);

                Notification::make()->title('Laporan dikembalikan untuk revisi')->warning()->send();
            })
            ->cancelParentActions();
    }
}
