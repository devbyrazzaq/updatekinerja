<?php

namespace App\Filament\Resources\TahunKerjas\Pages;

use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Resources\TahunKerjas\TahunKerjaResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewTahunKerja extends ViewRecord
{
    protected static string $resource = TahunKerjaResource::class;

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
            TahunKerjaResource::getUrl() => 'Data Tahun Kerja',
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
