<?php

namespace App\Filament\Actions;

use App\Enums\EnumStatusRealisasi;
use App\Models\RealisasiProgramKerja;
use App\Services\Notifikasi\NotifikasiVerifikasi;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

/**
 * Aksi "Minta Revisi": mengembalikan realisasi ke unit kerja untuk diperbaiki dan
 * diajukan ulang, disertai catatan revisi (rich editor) yang tersimpan di log,
 * ditampilkan pada Komentar, dan dikirim sebagai notifikasi ke pengaju.
 */
class RevisiRealisasiAction extends TahapVerifikasiRealisasiAction
{
    public static function getDefaultName(): ?string
    {
        return 'revisi';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Minta Revisi')
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->visible(fn (RealisasiProgramKerja $record): bool => static::bolehMemutuskan($record))
            ->modalHeading('Minta Revisi Realisasi')
            ->modalDescription('Sampaikan catatan perbaikan. Realisasi dikembalikan ke unit kerja untuk diperbaiki dan diajukan ulang.')
            ->modalSubmitActionLabel('Kirim Revisi')
            ->modalIcon('heroicon-o-pencil-square')
            ->modalIconColor('warning')
            ->modalWidth(Width::Large)
            ->schema([
                RichEditor::make('catatan')
                    ->label('Catatan Revisi')
                    ->required(),
            ])
            ->action(function (array $data, RealisasiProgramKerja $record): void {
                $tahap = static::tahap($record);

                if ($tahap === null) {
                    return;
                }

                $record->update([
                    'status' => EnumStatusRealisasi::Revisi,
                    'catatan_verifikasi' => $data['catatan'],
                    $tahap['actor'] => auth()->id(),
                    $tahap['timestamp'] => now(),
                ]);

                $record->catatLog(EnumStatusRealisasi::Revisi, auth()->id(), $data['catatan']);

                app(NotifikasiVerifikasi::class)->realisasiRevisi($record, $data['catatan']);

                Notification::make()->title('Permintaan revisi terkirim')->success()->send();
            });
    }
}
