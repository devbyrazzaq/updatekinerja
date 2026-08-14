<?php

namespace App\Filament\Resources\VerifikasiPengajuans\Pages;

use App\Enums\EnumStatusPengajuan;
use App\Filament\Resources\VerifikasiPengajuans\VerifikasiPengajuanResource;
use App\Models\PengajuanProgramKerja;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewVerifikasiPengajuan extends ViewRecord
{
    protected static string $resource = VerifikasiPengajuanResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Detail Pengajuan Program Kerja';
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            static::getResource()::getUrl() => 'Verifikasi Pengajuan',
            '' => 'Detail',
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('terima')
                ->label('Terima')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->dapatDiverifikasi())
                ->requiresConfirmation()
                ->modalHeading('Terima Pengajuan')
                ->modalDescription('Yakin ingin menerima pengajuan ini? Pengajuan akan disetujui dan tidak dapat diubah kembali.')
                ->modalSubmitActionLabel('Terima')
                ->action(fn () => $this->prosesVerifikasi(EnumStatusPengajuan::Diterima)),
            Action::make('revisi')
                ->label('Revisi')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn (): bool => $this->dapatDiverifikasi())
                ->requiresConfirmation()
                ->modalHeading('Minta Revisi')
                ->modalDescription('Pengaju akan diminta memperbaiki pengajuan sesuai catatan berikut.')
                ->modalSubmitActionLabel('Kirim Revisi')
                ->schema([
                    RichEditor::make('catatan')
                        ->label('Catatan Revisi')
                        ->required(),
                ])
                ->action(fn (array $data) => $this->prosesVerifikasi(EnumStatusPengajuan::Revisi, $data['catatan'] ?? null)),
        ];
    }

    /**
     * Aksi verifikasi hanya muncul untuk pengguna berwenang saat pengajuan masih
     * berstatus diajukan.
     */
    protected function dapatDiverifikasi(): bool
    {
        return static::getResource()::currentUserCanVerify()
            && static::getResource()::isPendingAtStage($this->getRecord());
    }

    /**
     * Menyimpan hasil verifikasi pada pengajuan sekaligus mencatatnya di riwayat.
     */
    protected function prosesVerifikasi(EnumStatusPengajuan $status, ?string $catatan = null): void
    {
        /** @var PengajuanProgramKerja $record */
        $record = $this->getRecord();

        $record->update([
            'status' => $status,
            'catatan_verifikasi' => $catatan,
            'verifikator_id' => auth()->id(),
            'diverifikasi_at' => now(),
        ]);

        $record->catatLog($status, auth()->id(), $catatan);

        Notification::make()->title('Verifikasi tersimpan')->success()->send();

        $this->redirect(static::getResource()::getUrl('index'));
    }
}
