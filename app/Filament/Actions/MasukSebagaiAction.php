<?php

namespace App\Filament\Actions;

use App\Models\User;
use App\Services\Impersonasi;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Livewire;

/**
 * Masuk sebagai pengguna lain ({@see Impersonasi}) dari menu Pengguna. Permission-nya
 * mengikuti resource halaman tempat aksi dipasang (`impersonate_<resource>`), jadi hak
 * masuk sebagai Dosen dan Tenaga Pendidik bisa diberikan terpisah.
 */
class MasukSebagaiAction extends AuthorizedAction
{
    public static function getDefaultName(): ?string
    {
        return 'masukSebagai';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Masuk Sebagai');
        $this->icon(Heroicon::OutlinedArrowRightEndOnRectangle);
        $this->permission(fn (Page $livewire): bool => (bool) auth()->user()?->can(
            $livewire::getResource()::getPermissionName('impersonate'),
        ));
        $this->hidden(fn (User $record): bool => ! Impersonasi::bolehMasukSebagai($record));

        $this->requiresConfirmation();
        $this->modalIcon(Heroicon::OutlinedArrowRightEndOnRectangle);
        $this->modalHeading('Masuk Sebagai Pengguna');
        $this->modalDescription(fn (User $record): string => "Anda akan melihat aplikasi sebagai {$record->getFullName()}, lengkap dengan menu dan hak aksesnya. Setiap perubahan yang dilakukan tercatat atas nama pengguna tersebut.");
        $this->modalSubmitActionLabel('Ya, Masuk');
        $this->modalCancelActionLabel('Batal');

        $this->action(function (User $record): void {
            Impersonasi::mulai($record, Livewire::originalUrl());

            $this->redirect(filament()->getUrl());
        });
    }
}
