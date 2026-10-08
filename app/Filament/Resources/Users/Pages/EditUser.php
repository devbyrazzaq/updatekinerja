<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Actions\AuthorizedViewAction;
use App\Filament\Actions\CaptchaDeleteAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

abstract class EditUser extends EditRecord
{
    /**
     * Role utama akun setelah disimpan: pilihan Jenis Akun pada menu multi-persona,
     * atau persona akun saat ini bila pilihannya tidak ikut terkirim (mis. dikunci).
     */
    protected ?string $personaTerpilih = null;

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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $personaRoles = $this->getResource()::personaRoles();

        $this->personaTerpilih = $data['persona_role']
            ?? $this->getRecord()->roles->pluck('name')->first(fn (string $role): bool => in_array($role, $personaRoles, true))
            ?? $personaRoles[0]
            ?? null;
        unset($data['persona_role']);

        return $data;
    }

    /**
     * Role utama menu (mis. Dosen) dipasang ulang setelah Filament menyinkronkan
     * pilihan role tambahan, sehingga akun tidak keluar dari menunya sendiri. Pada
     * menu multi-persona, persona lama dicabut bila jenis akunnya diganti — select
     * role tambahan tidak menyentuh role persona karena tidak termasuk opsinya.
     */
    protected function afterSave(): void
    {
        if ($this->personaTerpilih === null) {
            return;
        }

        $akun = $this->getRecord();

        foreach ($this->getResource()::personaRoles() as $persona) {
            if ($persona !== $this->personaTerpilih && $akun->hasRole($persona)) {
                $akun->removeRole($persona);
            }
        }

        $akun->assignRole($this->personaTerpilih);
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
