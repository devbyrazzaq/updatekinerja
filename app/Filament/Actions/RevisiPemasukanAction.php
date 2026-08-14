<?php

namespace App\Filament\Actions;

use App\Enums\EnumStatusPemasukan;
use App\Models\Pemasukan;
use App\Services\Notifikasi\NotifikasiVerifikasi;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

/**
 * Aksi "Minta Revisi": mengembalikan pemasukan ke unit kerja untuk diperbaiki dan
 * diajukan ulang, disertai catatan revisi yang tersimpan di riwayat dan dikirim
 * sebagai notifikasi ke pencatat pemasukan.
 */
class RevisiPemasukanAction extends TahapVerifikasiPemasukanAction
{
    public static function getDefaultName(): ?string
    {
        return 'revisiPemasukan';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Minta Revisi')
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->visible(fn (Pemasukan $record): bool => static::bolehMemutuskan($record))
            ->modalHeading('Minta Revisi Pemasukan')
            ->modalDescription('Sampaikan catatan perbaikan. Pemasukan dikembalikan ke unit kerja untuk diperbaiki dan diajukan ulang.')
            ->modalSubmitActionLabel('Kirim Revisi')
            ->modalIcon('heroicon-o-pencil-square')
            ->modalIconColor('warning')
            ->modalWidth(Width::Large)
            ->schema([
                RichEditor::make('catatan')
                    ->label('Catatan Revisi')
                    ->required(),
            ])
            ->action(function (array $data, Pemasukan $record): void {
                $tahap = static::tahap($record);

                if ($tahap === null) {
                    return;
                }

                $record->update([
                    'status' => EnumStatusPemasukan::Revisi,
                    'catatan_verifikasi' => $data['catatan'],
                    $tahap['actor'] => auth()->id(),
                    $tahap['timestamp'] => now(),
                ]);

                $record->catatLog(EnumStatusPemasukan::Revisi, auth()->id(), $data['catatan']);

                app(NotifikasiVerifikasi::class)->pemasukanRevisi($record, $data['catatan']);

                Notification::make()->title('Permintaan revisi terkirim')->success()->send();
            });
    }
}
