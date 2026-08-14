<?php

namespace App\Filament\Actions;

use App\Enums\EnumStatusRealisasi;
use App\Models\RealisasiProgramKerja;
use App\Services\Notifikasi\NotifikasiVerifikasi;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

/**
 * Aksi "Tolak": menolak realisasi secara permanen sehingga tidak dapat dilanjutkan
 * maupun diajukan ulang, disertai catatan alasan penolakan yang tersimpan di log,
 * ditampilkan pada Komentar, dan dikirim sebagai notifikasi ke pengaju.
 */
class TolakRealisasiAction extends TahapVerifikasiRealisasiAction
{
    public static function getDefaultName(): ?string
    {
        return 'tolak';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Tolak')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (RealisasiProgramKerja $record): bool => static::bolehMemutuskan($record))
            ->modalHeading('Tolak Realisasi')
            ->modalDescription('Realisasi yang ditolak tidak dapat dilanjutkan maupun diajukan ulang. Sampaikan alasan penolakan.')
            ->modalSubmitActionLabel('Tolak Realisasi')
            ->modalIcon('heroicon-o-x-circle')
            ->modalIconColor('danger')
            ->modalWidth(Width::Large)
            ->schema([
                RichEditor::make('catatan')
                    ->label('Alasan Penolakan')
                    ->required(),
            ])
            ->action(function (array $data, RealisasiProgramKerja $record): void {
                $tahap = static::tahap($record);

                if ($tahap === null) {
                    return;
                }

                $record->update([
                    'status' => EnumStatusRealisasi::Ditolak,
                    'catatan_verifikasi' => $data['catatan'],
                    $tahap['actor'] => auth()->id(),
                    $tahap['timestamp'] => now(),
                ]);

                $record->catatLog(EnumStatusRealisasi::Ditolak, auth()->id(), $data['catatan']);

                app(NotifikasiVerifikasi::class)->realisasiDitolak($record, $data['catatan']);

                Notification::make()->title('Realisasi ditolak')->success()->send();
            });
    }
}
