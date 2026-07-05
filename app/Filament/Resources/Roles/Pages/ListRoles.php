<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Actions\AuthorizedCreateAction;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Role & Akses';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Berikut adalah daftar role yang tersedia beserta hak aksesnya.';
    }

    /**
     * @return array<int, AuthorizedCreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            AuthorizedCreateAction::make()->label('Tambah Role'),
        ];
    }
}
