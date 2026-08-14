<?php

namespace App\Filament\Resources\Pemasukans\Pages;

use App\Filament\Resources\Pemasukans\PemasukanResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreatePemasukan extends CreateRecord
{
    protected static string $resource = PemasukanResource::class;

    public function getBreadcrumb(): string
    {
        return 'Tambah';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Catat Pemasukan Baru';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * Pencatat pemasukan diambil dari sesi, bukan dari field form, agar notifikasi
     * revisi/penolakan/permintaan bukti sampai tepat ke orangnya.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'user_id' => auth()->id()];
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
                ->url(fn (): string => PemasukanResource::getUrl('index')),
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
            ->title('Pemasukan berhasil dicatat')
            ->success();
    }
}
