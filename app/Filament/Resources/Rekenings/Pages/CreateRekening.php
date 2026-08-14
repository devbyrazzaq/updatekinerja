<?php

namespace App\Filament\Resources\Rekenings\Pages;

use App\Filament\Resources\Rekenings\RekeningResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateRekening extends CreateRecord
{
    protected static string $resource = RekeningResource::class;

    public function getBreadcrumb(): string
    {
        return 'Tambah';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Buat C.O.A Baru';
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
                ->url(fn (): string => RekeningResource::getUrl('index')),
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
            ->title('C.O.A berhasil dibuat')
            ->success();
    }
}
