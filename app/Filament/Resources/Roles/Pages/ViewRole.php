<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Resources\Roles\RoleResource;
use App\Services\PermissionRegistrar;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewRole extends ViewRecord
{
    protected static string $resource = RoleResource::class;

    protected string $view = 'filament.resources.roles.pages.view-role';

    public function getTitle(): string|Htmlable
    {
        return 'Detail Role '.$this->record->name;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            RoleResource::getUrl() => 'Role & Akses',
            '' => 'Detail Role '.$this->record->name,
        ];
    }

    /**
     * @return array<string, array{heading: string, description: string, groups: array<int, array{heading: string, description: string, labels: array<int, string>}>}>
     */
    public function getPermissionSections(): array
    {
        return PermissionRegistrar::getGrantedPermissionSections($this->record);
    }

    /**
     * @return array<int, AuthorizedEditAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            AuthorizedEditAction::make()
                ->label('Masuk ke Edit Mode')
                ->icon('heroicon-o-pencil-square'),
        ];
    }
}
