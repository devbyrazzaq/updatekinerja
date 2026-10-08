<?php

namespace App\Filament\Resources\Pemasukans\Schemas;

use App\Enums\EnumJenisWaktuPemasukan;
use App\Enums\EnumSumberPemasukan;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Resources\Pemasukans\PemasukanResource;
use App\Models\Pemasukan;
use App\Models\PengajuanProgramKerja;
use App\Models\RealisasiProgramKerja;
use App\Services\UnitKerjaAktif;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class PemasukanForm
{
    public static function configure(Schema $schema): Schema
    {
        $sumberIs = fn (EnumSumberPemasukan $sumber): \Closure => function (Get $get) use ($sumber): bool {
            $state = $get('sumber');

            return ($state instanceof EnumSumberPemasukan ? $state : EnumSumberPemasukan::tryFrom((string) $state)) === $sumber;
        };

        $isRentang = function (Get $get): bool {
            $state = $get('jenis_waktu');

            return (($state instanceof EnumJenisWaktuPemasukan ? $state : EnumJenisWaktuPemasukan::tryFrom((string) $state))
                ?->isRentang()) ?? false;
        };

        return $schema
            ->components([
                Section::make('Sumber Pemasukan')
                    ->description('Pilih program kerja yang menjadi sumber pemasukan unit.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('unit_kerja_id')
                                ->label('Unit Kerja')
                                ->relationship('unitKerja', 'name', fn (Builder $query): Builder => UnitKerjaAktif::batasiKueri($query, PemasukanResource::getPermissionName('view_any')))
                                ->searchable()->preload()->required()
                                ->default(fn (): ?int => UnitKerjaAktif::id())
                                ->columnSpan(1),
                            Select::make('sumber')
                                ->label('Jenis Sumber')
                                ->options(EnumSumberPemasukan::class)
                                ->required()
                                ->live()
                                ->columnSpan(1),
                            Select::make('pengajuan_program_kerja_id')
                                ->label('Pengajuan Program Kerja')
                                ->relationship('pengajuanProgramKerja', 'id')
                                ->getOptionLabelFromRecordUsing(fn (PengajuanProgramKerja $record): string => ($record->penawaranProgramKerja?->name ?? 'Pengajuan #'.$record->id).' — '.($record->unitKerja?->name ?? '-'))
                                ->searchable()->preload()
                                ->visible($sumberIs(EnumSumberPemasukan::Pengajuan))
                                ->required($sumberIs(EnumSumberPemasukan::Pengajuan))
                                ->columnSpanFull(),
                            Select::make('realisasi_program_kerja_id')
                                ->label('Realisasi Program Kerja')
                                ->relationship('realisasiProgramKerja', 'name')
                                ->getOptionLabelFromRecordUsing(fn (RealisasiProgramKerja $record): string => ($record->name ?? 'Realisasi #'.$record->id))
                                ->searchable()->preload()
                                ->visible($sumberIs(EnumSumberPemasukan::Realisasi))
                                ->required($sumberIs(EnumSumberPemasukan::Realisasi))
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Rincian Pemasukan')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('rincian_kegiatan')
                                ->label('Rincian Kegiatan')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Select::make('jenis_waktu')
                                ->label('Jenis Waktu')
                                ->options(EnumJenisWaktuPemasukan::class)
                                ->default(EnumJenisWaktuPemasukan::SatuHari)
                                ->required()
                                ->live()
                                // Nilai tanggal selesai yang tersisa dari pilihan rentang tidak
                                // boleh ikut tersimpan saat pengguna kembali ke kegiatan sehari.
                                ->afterStateUpdated(function (Set $set, $state): void {
                                    $jenis = $state instanceof EnumJenisWaktuPemasukan
                                        ? $state
                                        : EnumJenisWaktuPemasukan::tryFrom((string) $state);

                                    if (! ($jenis?->isRentang() ?? false)) {
                                        $set('tanggal_selesai', null);
                                    }
                                })
                                ->columnSpan(1),
                            DatePicker::make('tanggal_pelaksanaan')
                                ->label(fn (Get $get): string => $isRentang($get) ? 'Tanggal Mulai' : 'Tanggal Pelaksanaan')
                                ->required()
                                ->live()
                                ->columnSpan(1),
                            DatePicker::make('tanggal_selesai')
                                ->label('Tanggal Selesai')
                                ->visible($isRentang)
                                ->required($isRentang)
                                ->afterOrEqual('tanggal_pelaksanaan')
                                ->validationMessages([
                                    'after_or_equal' => 'Tanggal selesai tidak boleh mendahului tanggal mulai.',
                                ])
                                ->columnSpan(1),
                            MoneyInput::make('nominal_pendapatan')
                                ->label('Nominal Pendapatan')
                                ->required()
                                ->columnSpan(1),
                            RichEditor::make('keterangan')
                                ->label('Keterangan')
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline', 'strike',
                                    'bulletList', 'orderedList', 'link', 'undo', 'redo',
                                ])
                                ->columnSpanFull(),
                        ]),
                    ]),
                // Saat memperbaiki pemasukan yang dikembalikan, unit kerja perlu melihat
                // status berjalan dan permintaan verifikator tanpa berpindah ke halaman detail.
                Section::make('Status Pengajuan')
                    ->description('Posisi pemasukan pada alur verifikasi.')
                    ->icon('heroicon-o-flag')
                    ->columnSpanFull()
                    ->hiddenOn('create')
                    ->schema([
                        Text::make(fn (?Pemasukan $record): string => $record?->labelStatus() ?? '-')
                            ->weight(FontWeight::SemiBold),
                        Text::make(fn (?Pemasukan $record): HtmlString => new HtmlString($record?->catatan_verifikasi ?? ''))
                            ->visible(fn (?Pemasukan $record): bool => filled($record?->catatan_verifikasi)),
                    ]),
            ]);
    }
}
