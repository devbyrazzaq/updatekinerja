<?php

namespace App\Filament\Resources\PaguAnggarans\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaguAnggaranInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pagu Anggaran')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('tahunKerja.name')->label('Tahun Kerja'),
                        TextEntry::make('unitKerja.name')->label('Unit Kerja'),
                        TextEntry::make('amount')->label('Nominal Pagu')->money('IDR'),
                        TextEntry::make('description')->label('Keterangan')->html()->placeholder('-')->columnSpanFull(),
                        TextEntry::make('created_at')->label('Dibuat')->dateTime('d F Y H:i'),
                        TextEntry::make('updated_at')->label('Diperbarui')->dateTime('d F Y H:i'),
                    ]),
            ]);
    }
}
