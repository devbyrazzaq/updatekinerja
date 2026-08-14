<?php

namespace App\Filament\Actions;

use App\Enums\EnumStatusPemasukan;
use App\Models\Pemasukan;
use App\Services\Notifikasi\NotifikasiVerifikasi;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

/**
 * Aksi "Tolak": menolak pemasukan secara permanen sehingga tidak dapat dilanjutkan
 * maupun diajukan ulang, disertai alasan penolakan yang tersimpan di riwayat dan
 * dikirim sebagai notifikasi ke pencatat pemasukan.
 */
class TolakPemasukanAction extends TahapVerifikasiPemasukanAction
{
    public static function getDefaultName(): ?string
    {
        return 'tolakPemasukan';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Tolak')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Pemasukan $record): bool => static::bolehMemutuskan($record))
            ->modalHeading('Tolak Pemasukan')
            ->modalDescription('Pemasukan yang ditolak tidak dapat dilanjutkan maupun diajukan ulang, dan tidak akan tercatat pada buku anggaran. Sampaikan alasan penolakan.')
            ->modalSubmitActionLabel('Tolak Pemasukan')
            ->modalIcon('heroicon-o-x-circle')
            ->modalIconColor('danger')
            ->modalWidth(Width::Large)
            ->schema([
                RichEditor::make('catatan')
                    ->label('Alasan Penolakan')
                    ->required(),
            ])
            ->action(function (array $data, Pemasukan $record): void {
                $tahap = static::tahap($record);

                if ($tahap === null) {
                    return;
                }

                $record->update([
                    'status' => EnumStatusPemasukan::Ditolak,
                    'catatan_verifikasi' => $data['catatan'],
                    $tahap['actor'] => auth()->id(),
                    $tahap['timestamp'] => now(),
                ]);

                $record->catatLog(EnumStatusPemasukan::Ditolak, auth()->id(), $data['catatan']);

                app(NotifikasiVerifikasi::class)->pemasukanDitolak($record, $data['catatan']);

                Notification::make()->title('Pemasukan ditolak')->success()->send();
            });
    }
}
