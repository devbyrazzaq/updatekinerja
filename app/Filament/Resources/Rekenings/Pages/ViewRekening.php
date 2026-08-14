<?php

namespace App\Filament\Resources\Rekenings\Pages;

use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Resources\Rekenings\RekeningResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewRekening extends ViewRecord
{
    protected static string $resource = RekeningResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Detail '.$this->record->name;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            RekeningResource::getUrl() => 'Data C.O.A',
            '' => 'Detail '.$this->record->name,
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
