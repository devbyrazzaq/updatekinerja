<?php

namespace App\Filament\Resources\Periodes\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PeriodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Periode')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nama Periode')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            DateTimePicker::make('start_datetime')
                                ->label('Mulai')
                                ->required()
                                ->columnSpan(1),
                            DateTimePicker::make('end_datetime')
                                ->label('Selesai')
                                ->required()
                                ->after('start_datetime')
                                ->columnSpan(1),
                            Select::make('user_id')
                                ->label('Dibuat Oleh')
                                ->relationship('user', 'name')
                                ->searchable()
                                ->preload()
                                ->default(fn (): ?int => auth()->id())
                                ->columnSpan(1),
                            Toggle::make('is_active')
                                ->label('Status Aktif')
                                ->default(false)
                                ->columnSpan(1),
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
