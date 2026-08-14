<?php

namespace App\Filament\Resources\JadwalPencairans\Pages;

use App\Filament\Actions\AuthorizedCreateAction;
use App\Filament\Resources\JadwalPencairans\JadwalPencairanResource;
use App\Filament\Resources\JadwalPencairans\Widgets\JadwalPencairanOverview;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListJadwalPencairans extends ListRecords
{
    protected static string $resource = JadwalPencairanResource::class;

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Jadwal Pencairan';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Gelombang pencairan anggaran beserta total realisasi yang dijadwalkan padanya. Tandai dicairkan bila anggaran telah diserahkan ke unit kerja.';
    }

    /**
     * @return array<int, AuthorizedCreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            AuthorizedCreateAction::make()->label('Tambah Jadwal'),
        ];
    }

    /**
     * @return array<int, class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            JadwalPencairanOverview::class,
        ];
    }
}
