<?php

namespace App\Filament\Resources\KelompokAcuans\Pages;

use App\Filament\Actions\AuthorizedCreateAction;
use App\Filament\Resources\KelompokAcuans\KelompokAcuanResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListKelompokAcuans extends ListRecords
{
    protected static string $resource = KelompokAcuanResource::class;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Data Kelompok Acuan Program Kerja';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Kelompokkan acuan program kerja per rencana jangka menengah (mis. 2025 - 2030). Kelompok yang aktif menjadi acuan default pada menu Acuan Program Kerja.';
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            AuthorizedCreateAction::make()->label('Tambah Kelompok Acuan'),
        ];
    }
}
