<?php

namespace App\Filament\Resources\JadwalPencairans\Pages;

use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Resources\JadwalPencairans\JadwalPencairanResource;
use App\Filament\Resources\JadwalPencairans\Tables\JadwalPencairansTable;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewJadwalPencairan extends ViewRecord
{
    protected static string $resource = JadwalPencairanResource::class;

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
            JadwalPencairanResource::getUrl() => 'Jadwal Pencairan',
            '' => 'Detail',
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            JadwalPencairansTable::cairkanAction(),
            AuthorizedEditAction::make()
                ->label('Masuk ke Edit Mode')
                ->icon('heroicon-o-pencil-square'),
        ];
    }
}
