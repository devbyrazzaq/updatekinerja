<?php

namespace App\Filament\Resources\TahunKerjas\Pages;

use App\Filament\Actions\AuthorizedViewAction;
use App\Filament\Actions\CaptchaDeleteAction;
use App\Filament\Resources\TahunKerjas\TahunKerjaResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTahunKerja extends EditRecord
{
    protected static string $resource = TahunKerjaResource::class;

    public function getBreadcrumb(): string
    {
        return 'Ubah';
    }

    /**
     * @return array<int, AuthorizedViewAction|CaptchaDeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            AuthorizedViewAction::make()
                ->icon('heroicon-o-eye')
                ->label('Masuk Ke View Mode'),
            CaptchaDeleteAction::make(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Simpan Perubahan');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('Batal');
    }

    protected function getRedirectUrl(): ?string
    {
        return TahunKerjaResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getSavedNotification(): ?Notification
    {
        return parent::getSavedNotification()?->title('Data Tahun Kerja berhasil diperbarui');
    }
}
