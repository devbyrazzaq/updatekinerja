<?php

namespace App\Filament\Actions;

use App\Enums\EnumStatusPemasukan;
use App\Models\Pemasukan;
use App\Services\Notifikasi\NotifikasiVerifikasi;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

/**
 * Aksi "Setujui" pada verifikasi pemasukan: meneruskan pemasukan ke tahap berikutnya.
 *
 * Berbeda dengan verifikasi realisasi, verifikator pemasukan tidak menetapkan nominal
 * — nominal yang dicatat unit kerja diterima apa adanya, sehingga modalnya cukup
 * berisi konfirmasi dan catatan opsional.
 */
class SetujuiPemasukanAction extends TahapVerifikasiPemasukanAction
{
    public static function getDefaultName(): ?string
    {
        return 'setujuiPemasukan';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Setujui')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Pemasukan $record): bool => static::bolehMemutuskan($record))
            ->modalHeading('Setujui Pemasukan')
            ->modalDescription(fn (Pemasukan $record): string => static::keteranganTahap($record))
            ->modalSubmitActionLabel('Setujui')
            ->modalIcon('heroicon-o-check-badge')
            ->modalIconColor('success')
            ->modalWidth(Width::Large)
            ->schema([
                RichEditor::make('catatan')
                    ->label('Catatan (opsional)'),
            ])
            ->action(function (array $data, Pemasukan $record): void {
                $tahap = static::tahap($record);

                if ($tahap === null) {
                    return;
                }

                $record->update([
                    'status' => $tahap['nextStatus'],
                    $tahap['actor'] => auth()->id(),
                    $tahap['timestamp'] => now(),
                ]);

                $record->catatLog($tahap['nextStatus'], auth()->id(), $data['catatan'] ?? null);

                // Tahap terakhir menyerahkan bola kembali ke unit kerja: pemasukan baru sah
                // setelah bukti tanda terimanya diunggah.
                if ($tahap['nextStatus'] === EnumStatusPemasukan::MenungguBukti) {
                    app(NotifikasiVerifikasi::class)->pemasukanMenungguBukti($record);
                }

                Notification::make()->title('Pemasukan berhasil disetujui')->success()->send();
            });
    }

    /**
     * Keterangan konsekuensi persetujuan sesuai tahap tempat record berada.
     */
    protected static function keteranganTahap(Pemasukan $record): string
    {
        return match (static::tahap($record)['nextStatus'] ?? null) {
            EnumStatusPemasukan::VerifikasiKeuangan => 'Pemasukan diteruskan ke Biro Keuangan untuk diverifikasi.',
            EnumStatusPemasukan::MenungguBukti => 'Pemasukan disetujui. Unit kerja akan diminta mengunggah bukti tanda terima sebelum pemasukan dinyatakan valid.',
            default => 'Pemasukan diteruskan ke tahap berikutnya.',
        };
    }
}
