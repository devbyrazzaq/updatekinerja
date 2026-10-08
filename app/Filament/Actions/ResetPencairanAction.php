<?php

namespace App\Filament\Actions;

use App\Filament\Resources\JadwalPencairans\JadwalPencairanResource;
use App\Filament\Resources\JadwalPencairans\RelationManagers\RealisasiProgramKerjasRelationManager;
use App\Models\JadwalPencairan;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

/**
 * Membatalkan penandaan "sudah dicairkan" pada jadwal, mis. karena salah klik atau
 * anggaran ternyata belum diserahkan. Seluruh realisasinya kembali menunggu anggaran
 * diberikan, sehingga jadwal dapat diubah, dicairkan ulang, atau dihapus. Ditolak
 * selama ada realisasi yang laporannya sudah diserahkan.
 *
 * Status cair yang sudah tercatat ikut hilang, jadi memakai captcha, bukan sekadar
 * konfirmasi.
 */
class ResetPencairanAction extends Action
{
    use Concerns\HasArithmeticCaptcha;

    public static function getDefaultName(): ?string
    {
        return 'resetPencairan';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Reset Pencairan');
        $this->icon(Heroicon::OutlinedArrowUturnLeft);
        $this->color('danger');
        $this->visible(fn (JadwalPencairan $record): bool => $record->sudahDicairkan()
            && JadwalPencairanResource::currentUserCanReset());

        $this->modalIcon(Heroicon::OutlinedArrowUturnLeft);
        $this->modalIconColor('danger');
        $this->modalHeading('Reset Pencairan Jadwal');
        $this->modalSubmitActionLabel('Ya, Reset');
        $this->modalCancelActionLabel('Batal');
        $this->setUpArithmeticCaptcha();
        $this->schema(fn (JadwalPencairan $record): array => [
            Text::make('Penandaan "sudah dicairkan" dibatalkan. '.$record->jumlahRealisasi().' realisasi kembali berstatus "Menunggu Anggaran Diberikan" dengan cara pembayaran yang sama, dan jadwal dapat diubah atau dicairkan ulang.'),
            Textarea::make('alasan')
                ->label('Alasan Reset')
                ->required()
                ->rows(3)
                ->helperText('Tercatat pada riwayat setiap realisasi.'),
            ...$this->getCaptchaSchema(),
        ]);

        $this->action(function (array $data, JadwalPencairan $record, Component $livewire): void {
            if (! $this->captchaAnswerIsValid($data)) {
                $this->notifyWrongCaptchaAnswer();

                $this->halt();
            }

            $penghalang = $record->realisasiPenghalangReset();

            if ($penghalang->isNotEmpty()) {
                Notification::make()
                    ->title('Pencairan tidak dapat di-reset')
                    ->body('Laporan realisasi berikut sudah diserahkan atau diproses: '.$penghalang->pluck('name')->implode(', ').'.')
                    ->danger()
                    ->persistent()
                    ->send();

                return;
            }

            $jumlah = $record->resetPencairan(auth()->id(), $data['alasan']);

            $livewire->dispatch(RealisasiProgramKerjasRelationManager::EVENT_JADWAL_DIPERBARUI);

            Notification::make()
                ->title('Pencairan berhasil di-reset')
                ->body($jumlah.' realisasi kembali menunggu anggaran diberikan.')
                ->success()
                ->send();
        });
    }

    protected function getCaptchaLabel(): string
    {
        return 'Konfirmasi Reset';
    }

    protected function getCaptchaHelperText(): string
    {
        return 'Ketik hasil operasi untuk me-reset pencairan jadwal ini.';
    }
}
