<?php

namespace App\Filament\Resources\RealisasiProgramKerjas\Pages;

use App\Enums\EnumStatusRealisasi;
use App\Filament\Resources\RealisasiProgramKerjas\RealisasiProgramKerjaResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateRealisasiProgramKerja extends CreateRecord
{
    protected static string $resource = RealisasiProgramKerjaResource::class;

    public function getBreadcrumb(): string
    {
        return 'Tambah';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Buat Realisasi Program Kerja Baru';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    /**
     * Menandai bahwa unit kerja memilih langsung mengajukan realisasi (bukan
     * sekadar menyimpan draf) saat menekan tombol "Ajukan".
     */
    public bool $ajukanSetelahSimpan = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] ??= EnumStatusRealisasi::Draft->value;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->catatLog(EnumStatusRealisasi::Draft, auth()->id());

        if (! $this->ajukanSetelahSimpan) {
            return;
        }

        if (! $this->record->dapatDiajukan()) {
            $this->ajukanSetelahSimpan = false;

            Notification::make()
                ->title('Realisasi disimpan sebagai draf')
                ->body('Kuota realisasi berjalan unit kerja sudah penuh, sehingga realisasi belum dapat diajukan. Selesaikan realisasi yang sedang berjalan terlebih dahulu.')
                ->warning()
                ->send();

            return;
        }

        $this->record->update(['status' => EnumStatusRealisasi::Diajukan]);

        $this->record->catatLog(EnumStatusRealisasi::Diajukan, auth()->id());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Kembali')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url(fn (): string => RealisasiProgramKerjaResource::getUrl('index')),
        ];
    }

    /**
     * Tiga aksi form: mengajukan langsung, menyimpan sebagai draf, atau membatalkan.
     *
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getAjukanFormAction(),
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    /**
     * Menyimpan realisasi lalu langsung mengajukannya untuk verifikasi. Validasi
     * form tetap berjalan melalui `create()`.
     */
    protected function getAjukanFormAction(): Action
    {
        return Action::make('ajukan')
            ->label('Ajukan')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->action(function (): void {
                $this->ajukanSetelahSimpan = true;
                $this->create();
            });
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('Batal');
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Simpan sebagai Draf')
            ->color('gray');
    }

    protected function getCreatedNotification(): ?Notification
    {
        $title = $this->ajukanSetelahSimpan
            ? 'Realisasi Program Kerja berhasil diajukan'
            : 'Realisasi Program Kerja berhasil disimpan sebagai draf';

        return Notification::make()
            ->title($title)
            ->success();
    }
}
