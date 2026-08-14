<?php

namespace App\Filament\Resources\PengajuanProgramKerjas\Pages;

use App\Enums\EnumStatusPengajuan;
use App\Filament\Resources\PengajuanProgramKerjas\PengajuanProgramKerjaResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class CreatePengajuanProgramKerja extends CreateRecord
{
    protected static string $resource = PengajuanProgramKerjaResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * Status yang dipakai saat menyimpan, ditentukan oleh tombol yang ditekan.
     * Null berarti pengajuan langsung diajukan (tombol utama).
     */
    public ?string $statusToSave = null;

    public function getBreadcrumb(): string
    {
        return 'Tambah';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Buat Pengajuan Program Kerja Baru';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] ??= auth()->id();
        $data['status'] = $this->statusToSave ?? EnumStatusPengajuan::Diajukan->value;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->catatLog($this->record->status, $this->record->user_id);
    }

    /**
     * Simpan pengajuan sebagai draf alih-alih langsung diajukan.
     */
    public function createAsDraft(): void
    {
        $this->statusToSave = EnumStatusPengajuan::Draft->value;

        $this->create();
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

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getSaveAsDraftFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Ajukan')
            ->icon('heroicon-o-paper-airplane');
    }

    protected function getSaveAsDraftFormAction(): Action
    {
        return Action::make('saveAsDraft')
            ->label('Simpan sebagai Draft')
            ->icon('heroicon-o-document')
            ->color('gray')
            ->action('createAsDraft');
    }

    protected function getCreatedNotification(): ?Notification
    {
        $disimpanSebagaiDraft = $this->statusToSave === EnumStatusPengajuan::Draft->value;

        return Notification::make()
            ->title($disimpanSebagaiDraft
                ? 'Pengajuan disimpan sebagai draf'
                : 'Pengajuan berhasil diajukan')
            ->success();
    }
}
