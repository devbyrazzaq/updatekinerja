<?php

namespace App\Filament\Resources\Banks\Pages;

use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Resources\Banks\BankResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewBank extends ViewRecord
{
    protected static string $resource = BankResource::class;

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
            BankResource::getUrl() => 'Data Bank',
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
