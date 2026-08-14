<?php

namespace App\Filament\Resources\JadwalPencairans\Schemas;

use App\Models\TahunKerja;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JadwalPencairanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Jadwal Pencairan')
                    ->description('Beri nama jadwal dan tentukan tanggal anggaran akan diserahkan. Realisasi dijadwalkan ke jadwal ini dari menu Verifikasi Biro Keuangan.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Hidden::make('tahun_kerja_id')
                                ->default(fn (): ?int => TahunKerja::berjalan()?->id),
                            TextInput::make('name')
                                ->label('Nama Jadwal')
                                ->placeholder('Pencairan Awal Bulan Januari')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(1),
                            DatePicker::make('tanggal_pencairan')
                                ->label('Tanggal Pencairan')
                                ->required()
                                ->columnSpan(1),
                            RichEditor::make('catatan')
                                ->label('Catatan')
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
