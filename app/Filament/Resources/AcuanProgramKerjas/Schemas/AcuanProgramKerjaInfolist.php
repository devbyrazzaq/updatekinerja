<?php

namespace App\Filament\Resources\AcuanProgramKerjas\Schemas;

use App\Models\AcuanTarget;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AcuanProgramKerjaInfolist
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
                        TextEntry::make('kelompokAcuan.name')->label('Kelompok Acuan')->placeholder('-')->columnSpanFull(),
                        TextEntry::make('unitKerja.name')->label('Unit Kerja'),
                        TextEntry::make('bidang.name')->label('Bidang'),
                        TextEntry::make('kategori.name')->label('Kategori'),
                        TextEntry::make('program.name')->label('Program Induk'),
                        TextEntry::make('rekening.code')->label('Kode Akun')->placeholder('-'),
                        IconEntry::make('is_active')->label('Status Aktif')->boolean(),
                        TextEntry::make('nilai_standar')->label('Nilai Standar')->placeholder('-'),
                        TextEntry::make('satuan_nilai_standar')->label('Satuan Nilai Standar')->placeholder('-'),
                        TextEntry::make('aktifitas')->label('Aktivitas')->html()->placeholder('-')->columnSpanFull(),
                        TextEntry::make('indikator')->label('Indikator')->html()->placeholder('-')->columnSpanFull(),
                    ]),
                Section::make('Target per Tahun')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('targets')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Tahun')->width('120px'),
                                TableColumn::make('Nilai Target'),
                            ])
                            ->schema([
                                TextEntry::make('tahun'),
                                TextEntry::make('nilai')
                                    ->state(fn (AcuanTarget $record): string => trim("{$record->nilai} {$record->satuan}"))
                                    ->placeholder('-'),
                            ]),
                    ]),
                Section::make('Audit')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')->label('Dibuat')->dateTime('d F Y H:i'),
                        TextEntry::make('updated_at')->label('Diperbarui')->dateTime('d F Y H:i'),
                    ]),
            ]);
    }
}
