<?php

namespace App\Filament\Resources\PaguAnggarans\Pages;

use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Resources\PaguAnggarans\PaguAnggaranResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewPaguAnggaran extends ViewRecord
{
    protected static string $resource = PaguAnggaranResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Detail Pagu Anggaran';
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            PaguAnggaranResource::getUrl() => 'Data Pagu Anggaran',
            '' => 'Detail',
        ];
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
