<?php

namespace App\Filament\Resources\Banks\Pages;

use App\Filament\Actions\AuthorizedCreateAction;
use App\Filament\Resources\Banks\BankResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListBanks extends ListRecords
{
    protected static string $resource = BankResource::class;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Data Bank';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Daftar bank yang dapat dipakai pada rekening tujuan pencairan anggaran.';
    }

    /**
     * @return array<int, AuthorizedCreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            AuthorizedCreateAction::make()->label('Tambah Bank'),
        ];
    }
}
