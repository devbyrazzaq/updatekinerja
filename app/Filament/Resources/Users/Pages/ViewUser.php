<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Actions\MasukSebagaiAction;
use App\Filament\Actions\ResetKataSandiAction;
use App\Filament\Actions\UbahUsernameAction;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

abstract class ViewUser extends ViewRecord
{
    public function getTitle(): string|Htmlable
    {
        return 'Detail '.$this->record->name;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            static::getResource()::getUrl() => static::getResource()::getPluralModelLabel(),
            '' => 'Detail '.$this->record->name,
        ];
    }

    /**
     * Aksi akun yang jarang dipakai dikelompokkan, sedangkan masuk ke edit mode
     * tetap tombol utama.
     *
     * @return array<int, ActionGroup|AuthorizedEditAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                UbahUsernameAction::make(),
                ResetKataSandiAction::make(),
                MasukSebagaiAction::make(),
            ])
                ->label('Kelola Akun')
                ->icon(Heroicon::OutlinedUserCircle)
                ->color('gray')
                ->button(),
            AuthorizedEditAction::make()
                ->label('Masuk ke Edit Mode')
                ->icon('heroicon-o-pencil-square'),
        ];
    }
}
