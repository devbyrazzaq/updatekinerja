<?php

namespace App\Filament\Resources\Users\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

abstract class CreateUser extends CreateRecord
{
    protected ?string $generatedPassword = null;

    /**
     * Role utama yang dilekatkan pada akun baru: pilihan Jenis Akun pada menu
     * multi-persona, atau satu-satunya persona menu.
     */
    protected ?string $personaTerpilih = null;

    public function getBreadcrumb(): string
    {
        return 'Tambah';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Buat Pengguna Baru';
    }

    /**
     * Password awal = username + dua digit tanggal lahir (fallback: tanggal hari ini).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->generatedPassword = User::kataSandiAwal($data['username'], $data['birth_date'] ?? null);
        $data['password'] = $this->generatedPassword;

        $this->personaTerpilih = $data['persona_role'] ?? $this->getResource()::personaRoles()[0] ?? null;
        unset($data['persona_role']);

        return $data;
    }

    /**
     * Role utama menu (mis. Dosen) melekat setelah Filament menyinkronkan pilihan
     * role tambahan, sehingga tidak ikut terhapus oleh sinkronisasi tersebut.
     */
    protected function afterCreate(): void
    {
        if ($this->personaTerpilih !== null) {
            $this->record->assignRole($this->personaTerpilih);
        }
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
                ->url(fn (): string => static::getResource()::getUrl('index')),
        ];
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('Kembali');
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Simpan');
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()->label('Simpan dan Buat Lagi');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Pengguna berhasil dibuat')
            ->body("Username: {$this->record->username} — Password: {$this->generatedPassword}. Catat sekarang, kata sandi ini hanya ditampilkan sekali.")
            ->success()
            ->persistent();
    }
}
