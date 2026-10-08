<?php

namespace App\Filament\Actions;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * Mengganti username akun. Username dikunci pada form ubah karena menjadi nama
 * login sekaligus alamat halaman akun, jadi penggantiannya dipisah sebagai aksi
 * tersendiri dengan permission `update_username_<resource>`.
 */
class UbahUsernameAction extends AuthorizedAction
{
    public static function getDefaultName(): ?string
    {
        return 'ubahUsername';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Ubah Username');
        $this->icon(Heroicon::OutlinedAtSymbol);
        $this->permission(fn (Page $livewire, User $record): bool => $this->resource($livewire)::currentUserCanKelolaAkun('update_username', $record));

        $this->modalIcon(Heroicon::OutlinedAtSymbol);
        $this->modalHeading('Ubah Username');
        $this->modalDescription(fn (User $record): string => "Username baru langsung berlaku untuk login {$record->getFullName()}. Sampaikan perubahan ini kepada pemilik akun.");
        $this->modalSubmitActionLabel('Simpan Username');
        $this->modalCancelActionLabel('Batal');
        $this->modalWidth(Width::Medium);

        $this->fillForm(fn (User $record): array => ['username' => $record->username]);
        $this->schema([
            TextInput::make('username')
                ->label('Username Baru')
                ->required()
                ->maxLength(255)
                ->unique(table: User::class, column: 'username', ignorable: fn (User $record): User => $record),
        ]);

        $this->action(function (array $data, User $record, Page $livewire): void {
            $usernameLama = $record->username;

            $record->update(['username' => $data['username']]);

            Notification::make()
                ->title('Username berhasil diubah')
                ->body("{$usernameLama} → {$record->username}")
                ->success()
                ->send();

            // Username adalah route key akun, sehingga alamat halaman detailnya ikut
            // berubah; tanpa pengalihan, aksi berikutnya akan memuat alamat lama.
            if ($livewire instanceof ViewRecord) {
                $this->redirect($this->resource($livewire)::getUrl('view', ['record' => $record]));
            }
        });
    }

    /**
     * @return class-string<UserResource>
     */
    protected function resource(Page $livewire): string
    {
        return $livewire::getResource();
    }
}
