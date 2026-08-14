<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Actions\AuthorizedViewAction;
use App\Filament\Actions\CaptchaDeleteAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

abstract class EditUser extends EditRecord
{
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

    /**
     * Role utama menu (mis. Dosen) dipasang ulang setelah Filament menyinkronkan
     * pilihan role tambahan, sehingga akun tidak keluar dari menunya sendiri.
     */
    protected function afterSave(): void
    {
        $personaRole = $this->getResource()::$personaRole;

        if ($personaRole !== null) {
            $this->getRecord()->assignRole($personaRole);
        }
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
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getSavedNotification(): ?Notification
    {
        return parent::getSavedNotification()?->title('Data Pengguna berhasil diperbarui');
    }
}
