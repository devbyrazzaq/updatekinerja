<?php

namespace App\Filament\Resources\UnitKerjas\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UnitKerjaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Unit Kerja')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Nama Unit Kerja'),
                        IconEntry::make('is_active')->label('Status Aktif')->boolean(),
                        TextEntry::make('description')->label('Deskripsi')->html()->placeholder('-')->columnSpanFull(),
                        TextEntry::make('created_at')->label('Dibuat')->dateTime('d F Y H:i'),
                        TextEntry::make('updated_at')->label('Diperbarui')->dateTime('d F Y H:i'),
                    ]),
            ]);
    }
}
