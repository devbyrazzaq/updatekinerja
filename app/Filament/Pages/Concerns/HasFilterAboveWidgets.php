<?php

namespace App\Filament\Pages\Concerns;

use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

/**
 * Menempatkan panel penyaring "Tampilkan Data" (schema `form` halaman) di paling atas
 * halaman, mendahului widget ringkasan. Bawaan Filament merender widget header lebih
 * dulu, padahal angka widget itu justru ditentukan oleh penyaringnya, sehingga
 * penyaring perlu terbaca lebih dahulu.
 *
 * Halaman yang memakai trait ini tidak lagi merender `{{ $this->form }}` di bladenya.
 */
trait HasFilterAboveWidgets
{
    public function headerWidgets(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('form'),
            Grid::make($this->getHeaderWidgetsColumns())
                ->schema(fn (): array => $this->getWidgetsSchemaComponents($this->getHeaderWidgets())),
        ]);
    }
}
