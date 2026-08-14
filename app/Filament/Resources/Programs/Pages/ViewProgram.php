<?php

namespace App\Filament\Resources\Programs\Pages;

use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Resources\Programs\ProgramResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewProgram extends ViewRecord
{
    protected static string $resource = ProgramResource::class;

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
            ProgramResource::getUrl() => 'Data Program Induk',
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
