<?php

namespace App\Filament\Resources\RekeningBanks\Pages;

use App\Filament\Resources\RekeningBanks\RekeningBankResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateRekeningBank extends CreateRecord
{
    protected static string $resource = RekeningBankResource::class;

    public function getBreadcrumb(): string
    {
        return 'Tambah';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Buat Rekening Bank Baru';
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
                ->url(fn (): string => RekeningBankResource::getUrl('index')),
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
            ->title('Rekening bank berhasil dibuat')
            ->success();
    }
}
