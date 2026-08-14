<?php

namespace App\Filament\Resources\KelompokAcuans\Schemas;

use App\Models\Setting;
use Closure;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class KelompokAcuanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Kelompok Acuan')
                    ->description(fn (): string => 'Kelompok acuan mewakili rencana program kerja satu periode jabatan, yaitu '.Setting::tahunPerPeriode().' tahun (diatur di menu Pengaturan Sistem).')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nama Kelompok Acuan')
                                ->placeholder('Program Kerja '.date('Y').' - '.(date('Y') + Setting::tahunPerPeriode() - 1))
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            TextInput::make('tahun_mulai')
                                ->label('Tahun Mulai')
                                ->numeric()
                                ->required()
                                ->minValue(1900)
                                ->maxValue(2200)
                                ->default((int) date('Y'))
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Set $set, ?string $state): void {
                                    if (blank($state)) {
                                        return;
                                    }

                                    $set('tahun_selesai', (int) $state + Setting::tahunPerPeriode() - 1);
                                })
                                ->columnSpan(1),
                            TextInput::make('tahun_selesai')
                                ->label('Tahun Selesai')
                                ->numeric()
                                ->required()
                                ->minValue(1900)
                                ->maxValue(2200)
                                ->default((int) date('Y') + Setting::tahunPerPeriode() - 1)
                                ->helperText(fn (): string => 'Satu periode jabatan berjumlah '.Setting::tahunPerPeriode().' tahun (dihitung inklusif). Terisi otomatis dari tahun mulai.')
                                ->rule(static function (Get $get): Closure {
                                    return static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                        $tahunMulai = (int) $get('tahun_mulai');

                                        if ($tahunMulai <= 0) {
                                            return;
                                        }

                                        $jumlahTahun = Setting::tahunPerPeriode();
                                        $tahunSelesai = $tahunMulai + $jumlahTahun - 1;

                                        if ((int) $value !== $tahunSelesai) {
                                            $fail("Satu periode jabatan berjumlah {$jumlahTahun} tahun, sehingga tahun selesai harus {$tahunSelesai}.");
                                        }
                                    };
                                })
                                ->columnSpan(1),
                            Toggle::make('is_active')
                                ->label('Jadikan Kelompok Aktif')
                                ->helperText('Hanya satu kelompok yang dapat aktif. Menu Acuan Program Kerja menampilkan acuan dari kelompok aktif secara default.')
                                ->default(false)
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
