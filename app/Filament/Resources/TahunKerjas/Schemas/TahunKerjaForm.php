<?php

namespace App\Filament\Resources\TahunKerjas\Schemas;

use App\Enums\EnumStatusTahunKerja;
use App\Models\TahunKerja;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class TahunKerjaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Tahun Kerja')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('periode_id')
                                ->label('Periode')
                                ->relationship('periode', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->columnSpanFull(),
                            TextInput::make('name')
                                ->label('Nama Tahun Kerja')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(1),
                            TextInput::make('tahun')
                                ->label('Tahun')
                                ->numeric()
                                ->required()
                                ->minValue(2000)
                                ->maxValue(2100)
                                ->default(fn (): int => now()->year)
                                ->helperText('Menentukan target tahun berapa pada Acuan Program Kerja yang dipakai saat Penawaran Program Kerja dibentuk.')
                                ->columnSpan(1),
                            DateTimePicker::make('start_datetime')
                                ->label('Mulai')
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Set $set, ?string $state): void {
                                    if (filled($state)) {
                                        $set('tahun', Carbon::parse($state)->year);
                                    }
                                })
                                ->columnSpan(1),
                            DateTimePicker::make('end_datetime')
                                ->label('Selesai')
                                ->required()
                                ->after('start_datetime')
                                ->columnSpan(1),
                            Placeholder::make('status')
                                ->label('Status')
                                ->content(fn (?TahunKerja $record): string => $record?->status?->getLabel() ?? EnumStatusTahunKerja::Selesai->getLabel())
                                ->helperText('Status hanya berpindah lewat halaman Pengaturan Program Kerja agar tetap ada paling banyak satu tahun kerja berjalan dan satu tahun kerja perencanaan.')
                                ->columnSpanFull(),
                            RichEditor::make('description')
                                ->label('Deskripsi')
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline', 'strike',
                                    'bulletList', 'orderedList', 'link', 'undo', 'redo',
                                ])
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }
}
