<?php

namespace App\Filament\Resources\RekeningBanks\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RekeningBankInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Rekening Bank')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('bank.name')->label('Nama Bank'),
                        TextEntry::make('nomor_rekening')->label('Nomor Rekening')->copyable(),
                        TextEntry::make('atas_nama')->label('Atas Nama'),
                        TextEntry::make('unitKerja.name')->label('Unit Kerja')->placeholder('Umum (semua unit)'),
                        IconEntry::make('is_utama')->label('Rekening Utama')->boolean(),
                        IconEntry::make('is_active')->label('Status Aktif')->boolean(),
                        TextEntry::make('description')->label('Keterangan')->html()->placeholder('-')->columnSpanFull(),
                        TextEntry::make('created_at')->label('Dibuat')->dateTime('d F Y H:i'),
                        TextEntry::make('updated_at')->label('Diperbarui')->dateTime('d F Y H:i'),
                    ]),
            ]);
    }
}
