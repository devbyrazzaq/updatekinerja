<?php

namespace App\Filament\Resources\TahunKerjas\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TahunKerjaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Tahun Kerja')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('periode.name')->label('Periode'),
                        TextEntry::make('name')->label('Nama Tahun Kerja'),
                        TextEntry::make('tahun')->label('Tahun')->badge()->color('gray')->placeholder('-'),
                        TextEntry::make('start_datetime')->label('Mulai')->dateTime('d F Y H:i'),
                        TextEntry::make('end_datetime')->label('Selesai')->dateTime('d F Y H:i'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('kelompokAcuan.name')->label('Kelompok Acuan')->placeholder('-'),
                        TextEntry::make('ditutup_pada')->label('Diakhiri')->dateTime('d F Y H:i')->placeholder('Belum diakhiri'),
                        TextEntry::make('ditutupOleh.name')->label('Diakhiri Oleh')->placeholder('-'),
                        TextEntry::make('dikunci_pada')->label('Dikunci')->dateTime('d F Y H:i')->placeholder('Belum dikunci'),
                        TextEntry::make('dikunciOleh.name')->label('Dikunci Oleh')->placeholder('-'),
                        TextEntry::make('description')->label('Deskripsi')->html()->placeholder('-')->columnSpanFull(),
                        TextEntry::make('created_at')->label('Dibuat')->dateTime('d F Y H:i'),
                        TextEntry::make('updated_at')->label('Diperbarui')->dateTime('d F Y H:i'),
                    ]),
            ]);
    }
}
