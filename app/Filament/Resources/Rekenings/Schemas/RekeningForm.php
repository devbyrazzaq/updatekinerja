<?php

namespace App\Filament\Resources\Rekenings\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RekeningForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi C.O.A')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('Kode Akun')
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true)
                                ->columnSpan(1),
                            TextInput::make('name')
                                ->label('Nama C.O.A')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(1),
                            Select::make('unit_kerja_id')
                                ->label('Unit Kerja')
                                ->relationship('unitKerja', 'name')
                                ->searchable()
                                ->preload()
                                ->placeholder('Semua unit (umum)')
                                ->helperText('Kosongkan bila C.O.A berlaku umum untuk semua unit kerja.')
                                ->columnSpanFull(),
                            RichEditor::make('description')
                                ->label('Deskripsi')
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline', 'strike',
                                    'bulletList', 'orderedList', 'link', 'undo', 'redo',
                                ])
                                ->columnSpanFull(),
                            Toggle::make('is_active')
                                ->label('Status Aktif')
                                ->default(true)
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }
}
