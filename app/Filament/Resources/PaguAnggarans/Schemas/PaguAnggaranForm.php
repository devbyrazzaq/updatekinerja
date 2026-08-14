<?php

namespace App\Filament\Resources\PaguAnggarans\Schemas;

use App\Filament\Forms\Components\MoneyInput;
use App\Models\TahunKerja;
use App\Services\KonteksProgramKerja;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PaguAnggaranForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pagu Anggaran')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('tahun_kerja_id')
                                ->label('Tahun Kerja')
                                ->relationship(
                                    'tahunKerja',
                                    'name',
                                    fn (Builder $query): Builder => $query->whereIn('id', KonteksProgramKerja::tahunPerencanaanIds()),
                                )
                                ->searchable()
                                ->preload()
                                ->required()
                                ->default(fn (): ?int => TahunKerja::berjalan()?->id)
                                ->helperText('Pagu bisa disusun untuk tahun kerja yang sedang berjalan maupun tahun yang sedang direncanakan.')
                                ->columnSpan(1),
                            Select::make('unit_kerja_id')
                                ->label('Unit Kerja')
                                ->relationship('unitKerja', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->columnSpan(1),
                            MoneyInput::make('amount')
                                ->label('Nominal Pagu')
                                ->required()
                                ->columnSpanFull(),
                            RichEditor::make('description')
                                ->label('Keterangan')
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
