<?php

namespace App\Filament\Resources\Banks\Schemas;

use App\Models\Bank;
use Closure;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BankForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Bank')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema(static::components()),
                    ]),
            ]);
    }

    /**
     * Komponen inti form, dipakai ulang oleh modal "tambah bank" pada select nama bank
     * saat menambah rekening bank.
     *
     * @return array<int, TextInput|RichEditor|Toggle>
     */
    public static function components(): array
    {
        return [
            // Tabel dan record acuan ditulis eksplisit karena komponen ini juga dipakai
            // pada modal "tambah bank" yang model schema-nya bukan Bank, sehingga
            // penentuan tabel maupun record otomatis akan salah sasaran.
            TextInput::make('name')
                ->label('Nama Bank')
                ->placeholder('mis. Bank Syariah Indonesia')
                ->required()
                ->maxLength(255)
                ->unique(table: Bank::class, ignorable: static::bankTerkait(), ignoreRecord: false)
                ->columnSpan(1),
            TextInput::make('code')
                ->label('Kode Bank')
                ->placeholder('mis. 451')
                ->maxLength(255)
                ->unique(table: Bank::class, ignorable: static::bankTerkait(), ignoreRecord: false)
                ->helperText('Opsional, kode bank untuk keperluan transfer.')
                ->columnSpan(1),
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
        ];
    }

    /**
     * Record yang dikecualikan dari pemeriksaan keunikan: hanya bank yang sedang
     * diubah, bukan record milik halaman lain yang menampung modal "tambah bank".
     */
    protected static function bankTerkait(): Closure
    {
        return fn (mixed $record): ?Bank => $record instanceof Bank ? $record : null;
    }
}
