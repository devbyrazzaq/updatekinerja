<?php

namespace App\Filament\Resources\DaftarProgramKerjas\Pages;

use App\Filament\Resources\DaftarProgramKerjas\DaftarProgramKerjaResource;
use App\Filament\Resources\DaftarProgramKerjas\Tables\DaftarProgramKerjasTable;
use App\Filament\Resources\DaftarProgramKerjas\Widgets\ProgramKerjaRealisasiOverview;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewDaftarProgramKerja extends ViewRecord
{
    protected static string $resource = DaftarProgramKerjaResource::class;

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
            static::getResource()::getUrl() => 'Daftar Program Kerja',
            '' => 'Detail '.$this->record->name,
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            DaftarProgramKerjasTable::ajukanAction()
                ->after(fn () => $this->record->refresh()),
        ];
    }

    /**
     * @return array<int, class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            ProgramKerjaRealisasiOverview::class,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            'record' => $this->record,
        ];
    }
}
