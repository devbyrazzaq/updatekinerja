<?php

namespace App\Filament\Resources\Periodes\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PeriodeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Periode')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Nama Periode'),
                        IconEntry::make('is_active')->label('Status Aktif')->boolean(),
                        TextEntry::make('start_datetime')->label('Mulai')->dateTime('d F Y H:i'),
                        TextEntry::make('end_datetime')->label('Selesai')->dateTime('d F Y H:i'),
                        TextEntry::make('user.name')->label('Dibuat Oleh')->placeholder('-'),
                        TextEntry::make('description')->label('Deskripsi')->html()->placeholder('-')->columnSpanFull(),
                        TextEntry::make('created_at')->label('Dibuat')->dateTime('d F Y H:i'),
                        TextEntry::make('updated_at')->label('Diperbarui')->dateTime('d F Y H:i'),
                    ]),
            ]);
    }
}
