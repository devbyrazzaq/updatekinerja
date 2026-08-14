<?php

namespace App\Filament\Resources\PenawaranProgramKerjas\Pages;

use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Resources\PenawaranProgramKerjas\PenawaranProgramKerjaResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewPenawaranProgramKerja extends ViewRecord
{
    protected static string $resource = PenawaranProgramKerjaResource::class;

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
            PenawaranProgramKerjaResource::getUrl() => 'Data Penawaran Program Kerja',
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
