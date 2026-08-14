<?php

namespace App\Filament\Resources\PenawaranProgramKerjas\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PenawaranProgramKerjaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Program Kerja')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Nama Program Kerja')->columnSpanFull(),
                        TextEntry::make('tahunKerja.name')->label('Tahun Kerja'),
                        TextEntry::make('unitKerja.name')->label('Unit Kerja'),
                        TextEntry::make('bidang.name')->label('Bidang'),
                        TextEntry::make('kategori.name')->label('Kategori'),
                        TextEntry::make('program.name')->label('Program Induk'),
                        TextEntry::make('rekening.code')->label('Kode Akun')->placeholder('-'),
                        TextEntry::make('target')->label('Target')->placeholder('-'),
                        TextEntry::make('nilai_standar')->label('Nilai Standar')->placeholder('-'),
                        TextEntry::make('satuan_nilai_standar')->label('Satuan Nilai Standar')->placeholder('-'),
                        IconEntry::make('is_active')->label('Status Aktif')->boolean(),
                        TextEntry::make('aktifitas')->label('Aktivitas')->html()->placeholder('-')->columnSpanFull(),
                        TextEntry::make('indikator')->label('Indikator')->html()->placeholder('-')->columnSpanFull(),
                        TextEntry::make('created_at')->label('Dibuat')->dateTime('d F Y H:i'),
                        TextEntry::make('updated_at')->label('Diperbarui')->dateTime('d F Y H:i'),
                    ]),
            ]);
    }
}
