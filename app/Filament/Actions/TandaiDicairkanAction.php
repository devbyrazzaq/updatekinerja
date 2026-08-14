<?php

namespace App\Filament\Actions;

use App\Enums\EnumStatusRealisasi;
use App\Filament\Resources\Concerns\HasPencairanPembayaran;
use App\Filament\Resources\VerifikasiBiroKeuangans\VerifikasiBiroKeuanganResource;
use App\Models\RealisasiProgramKerja;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

/**
 * Menandai anggaran sudah dicairkan beserta cara pembayarannya, sehingga unit kerja
 * diminta melaporkan pelaksanaan kegiatannya.
 */
class TandaiDicairkanAction extends Action
{
    use HasPencairanPembayaran;

    public static function getDefaultName(): ?string
    {
        return 'tandaiDicairkan';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Tandai Dicairkan')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->modalHeading('Tandai Anggaran Dicairkan')
            ->modalDescription('Anggaran dinyatakan sudah diberikan ke unit kerja. Periksa kembali cara pembayaran yang dipilih saat penjadwalan, lalu tandai dicairkan.')
            ->modalSubmitActionLabel('Tandai Dicairkan')
            ->modalIcon('heroicon-o-check-circle')
            ->modalIconColor('success')
            ->modalWidth(Width::Medium)
            ->fillForm(fn (RealisasiProgramKerja $record): array => static::nilaiAwalPembayaran($record))
            ->schema(static::skemaPembayaran())
            ->visible(fn (RealisasiProgramKerja $record): bool => VerifikasiBiroKeuanganResource::currentUserCanVerify()
                && $record->status === EnumStatusRealisasi::Dijadwalkan)
            ->action(function (array $data, RealisasiProgramKerja $record): void {
                $record->tandaiAnggaranDicairkan(
                    auth()->id(),
                    static::metodePembayaranDari($data),
                    static::rekeningBankDari($data),
                );

                Notification::make()
                    ->title('Anggaran ditandai sudah dicairkan')
                    ->body('Unit kerja kini dapat mengirim laporan realisasi.')
                    ->success()
                    ->send();
            });
    }
}
