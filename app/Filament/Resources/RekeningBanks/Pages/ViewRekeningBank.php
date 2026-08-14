<?php

namespace App\Filament\Resources\RekeningBanks\Pages;

use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Resources\RekeningBanks\RekeningBankResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewRekeningBank extends ViewRecord
{
    protected static string $resource = RekeningBankResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Detail Rekening '.$this->record->nomor_rekening;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            RekeningBankResource::getUrl() => 'Data Rekening Bank',
            '' => 'Detail Rekening '.$this->record->nomor_rekening,
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
