<?php

namespace App\Filament\Resources\AcuanProgramKerjas\Schemas;

use App\Models\KelompokAcuan;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class AcuanProgramKerjaForm
{
    /**
     * @param  int|null  $kelompokAcuanId  Bila diisi, kelompok acuan dikunci (mis. saat
     *                                     menambah acuan dari halaman Kelompok Acuan)
     *                                     sehingga field pemilih kelompok disembunyikan.
     */
    public static function configure(Schema $schema, ?int $kelompokAcuanId = null): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Program Kerja')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            self::kelompokAcuanField($kelompokAcuanId),
                            TextInput::make('name')
                                ->label('Nama Program Kerja')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Select::make('unit_kerja_id')
                                ->label('Unit Kerja')
                                ->relationship('unitKerja', 'name')
                                ->searchable()->preload()->required()->columnSpan(1),
                            Select::make('bidang_id')
                                ->label('Bidang')
                                ->relationship('bidang', 'name')
                                ->searchable()->preload()->required()->columnSpan(1),
                            Select::make('kategori_id')
                                ->label('Kategori')
                                ->relationship('kategori', 'name')
                                ->searchable()->preload()->required()->columnSpan(1),
                            Select::make('program_id')
                                ->label('Program Induk')
                                ->relationship('program', 'name')
                                ->searchable()->preload()->required()->columnSpan(1),
                            Select::make('rekening_id')
                                ->label('Kode Akun')
                                ->relationship('rekening', 'code')
                                ->searchable()->preload()->columnSpan(1),
                            TextInput::make('nilai_standar')
                                ->label('Nilai Standar')
                                ->columnSpan(1),
                            TextInput::make('satuan_nilai_standar')
                                ->label('Satuan Nilai Standar')
                                ->columnSpan(1),
                            Toggle::make('is_active')
                                ->label('Status Aktif')
                                ->default(true)
                                ->columnSpan(1),
                            RichEditor::make('aktifitas')
                                ->label('Aktivitas')
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline', 'strike',
                                    'bulletList', 'orderedList', 'link', 'undo', 'redo',
                                ])
                                ->columnSpanFull(),
                            RichEditor::make('indikator')
                                ->label('Indikator')
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline', 'strike',
                                    'bulletList', 'orderedList', 'link', 'undo', 'redo',
                                ])
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Target per Tahun')
                    ->description('Satu baris untuk setiap tahun pada kelompok acuan yang dipilih. Isi nilai target beserta satuannya, mis. 90 persen.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('targets')
                            ->relationship()
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Tahun')
                                    ->markAsRequired()
                                    ->width('160px'),
                                TableColumn::make('Nilai Target')
                                    ->markAsRequired(),
                                TableColumn::make('Satuan'),
                            ])
                            ->schema([
                                Select::make('tahun')
                                    ->label('Tahun')
                                    ->options(fn (Get $get): array => self::opsiTahun($get('../../kelompok_acuan_id')))
                                    ->required()
                                    ->distinct(),
                                TextInput::make('nilai')
                                    ->label('Nilai Target')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('satuan')
                                    ->label('Satuan')
                                    ->placeholder('persen, kegiatan, orang, ...')
                                    ->maxLength(255),
                            ])
                            ->addActionLabel('Tambah Target')
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Pemilih kelompok acuan. Saat kelompok dikunci (dari halaman Kelompok Acuan),
     * field diganti input tersembunyi berisi id kelompok tersebut.
     */
    protected static function kelompokAcuanField(?int $kelompokAcuanId): Field
    {
        if ($kelompokAcuanId !== null) {
            return Hidden::make('kelompok_acuan_id')->default($kelompokAcuanId);
        }

        return Select::make('kelompok_acuan_id')
            ->label('Kelompok Acuan')
            ->relationship('kelompokAcuan', 'name')
            ->searchable()->preload()->required()
            ->default(fn (): ?int => KelompokAcuan::active()?->id)
            ->helperText('Rencana satu periode jabatan yang menaungi acuan ini. Tahun kelompok ini menentukan baris target di bawah.')
            ->live()
            ->afterStateUpdated(fn (Get $get, Set $set, mixed $state) => self::isiBarisTargetPerTahun($get, $set, $state))
            ->columnSpanFull();
    }

    /**
     * Opsi tahun sebuah kelompok acuan, mis. [2025 => '2025', ..., 2029 => '2029'].
     *
     * @return array<int, string>
     */
    protected static function opsiTahun(mixed $kelompokAcuanId): array
    {
        $tahunList = KelompokAcuan::find($kelompokAcuanId)?->tahunList() ?? [];

        return array_combine($tahunList, array_map('strval', $tahunList));
    }

    /**
     * Satu baris target kosong untuk tiap tahun kelompok acuan, siap dipakai sebagai
     * isian awal repeater target.
     *
     * @return array<string, array{tahun: int, nilai: null, satuan: null}>
     */
    public static function defaultTargetRows(mixed $kelompokAcuanId): array
    {
        return collect(KelompokAcuan::find($kelompokAcuanId)?->tahunList() ?? [])
            ->mapWithKeys(fn (int $tahun): array => [
                (string) Str::uuid() => ['tahun' => $tahun, 'nilai' => null, 'satuan' => null],
            ])
            ->all();
    }

    /**
     * Siapkan satu baris target untuk tiap tahun kelompok acuan yang baru dipilih.
     * Isian target yang sudah terisi tidak ditimpa.
     */
    protected static function isiBarisTargetPerTahun(Get $get, Set $set, mixed $kelompokAcuanId): void
    {
        $targets = collect($get('targets') ?? []);

        if ($targets->contains(fn (array $target): bool => filled($target['nilai'] ?? null))) {
            return;
        }

        $set('targets', self::defaultTargetRows($kelompokAcuanId));
    }
}
