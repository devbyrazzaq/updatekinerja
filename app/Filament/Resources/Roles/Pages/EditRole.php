<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Actions\AuthorizedViewAction;
use App\Filament\Actions\CaptchaDeleteAction;
use App\Filament\Resources\Roles\RoleResource;
use App\Services\PermissionRegistrar;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Ubah Role '.$this->record->name;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return PermissionRegistrar::fillFormData(
            $data,
            $this->getRecord()->loadMissing('permissions')->permissions->pluck('name')->all(),
        );
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

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Simpan Perubahan');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('Batal');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        PermissionRegistrar::syncToDatabase();

        $permissions = PermissionRegistrar::extractFromData($data);
        $record->update(PermissionRegistrar::stripPermissionFields($data));
        $record->syncPermissions($permissions);

        return $record;
    }

    protected function getRedirectUrl(): ?string
    {
        return RoleResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getSavedNotification(): ?Notification
    {
        return parent::getSavedNotification()?->title('Data role berhasil diperbarui');
    }
}
