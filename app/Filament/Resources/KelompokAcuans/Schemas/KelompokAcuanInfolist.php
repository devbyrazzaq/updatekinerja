<?php

namespace App\Filament\Resources\KelompokAcuans\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class KelompokAcuanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Kelompok Acuan')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Nama Kelompok Acuan')->columnSpanFull(),
                        TextEntry::make('tahun_mulai')->label('Tahun Mulai'),
                        TextEntry::make('tahun_selesai')->label('Tahun Selesai'),
                        IconEntry::make('is_active')->label('Status Aktif')->boolean(),
                        TextEntry::make('acuan_program_kerjas_count')
                            ->label('Jumlah Acuan')
                            ->state(fn ($record): int => $record->acuanProgramKerjas()->count())
                            ->badge(),
                        TextEntry::make('description')->label('Deskripsi')->html()->placeholder('-')->columnSpanFull(),
                        TextEntry::make('created_at')->label('Dibuat')->dateTime('d F Y H:i'),
                        TextEntry::make('updated_at')->label('Diperbarui')->dateTime('d F Y H:i'),
                    ]),
            ]);
    }
}
