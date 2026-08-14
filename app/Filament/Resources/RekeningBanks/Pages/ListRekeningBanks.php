<?php

namespace App\Filament\Resources\RekeningBanks\Pages;

use App\Filament\Actions\AuthorizedCreateAction;
use App\Filament\Resources\RekeningBanks\RekeningBankResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListRekeningBanks extends ListRecords
{
    protected static string $resource = RekeningBankResource::class;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Data Rekening Bank';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Rekening tujuan pencairan anggaran per unit kerja. Rekening utama unit kerja terpilih otomatis saat penjadwalan pencairan.';
    }

    /**
     * @return array<int, AuthorizedCreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            AuthorizedCreateAction::make()->label('Tambah Rekening'),
        ];
    }
}
