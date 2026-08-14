<?php

namespace App\Filament\Resources\PenawaranProgramKerjas\Schemas;

use App\Models\AcuanProgramKerja;
use App\Models\AcuanTarget;
use App\Models\TahunKerja;
use App\Services\KonteksProgramKerja;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PenawaranProgramKerjaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sumber Acuan')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('acuan_program_kerja_id')
                                ->label('Acuan Program Kerja')
                                ->relationship('acuanProgramKerja', 'name')
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                                    if ($state === null) {
                                        return;
                                    }

                                    $acuan = AcuanProgramKerja::find($state);

                                    if ($acuan === null) {
                                        return;
                                    }

                                    $set('name', $acuan->name);
                                    $set('unit_kerja_id', $acuan->unit_kerja_id);
                                    $set('bidang_id', $acuan->bidang_id);
                                    $set('kategori_id', $acuan->kategori_id);
                                    $set('program_id', $acuan->program_id);
                                    $set('rekening_id', $acuan->rekening_id);
                                    $set('nilai_standar', $acuan->nilai_standar);
                                    $set('satuan_nilai_standar', $acuan->satuan_nilai_standar);
                                    $set('aktifitas', $acuan->aktifitas);
                                    $set('indikator', $acuan->indikator);

                                    self::syncTargetFromAcuan($state, $get('tahun_kerja_id'), $set);
                                })
                                ->columnSpan(1),
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
                                ->live()
                                ->default(fn (): ?int => TahunKerja::berjalan()?->id)
                                ->afterStateUpdated(fn (?string $state, Set $set, Get $get) => self::syncTargetFromAcuan($get('acuan_program_kerja_id'), $state, $set))
                                ->columnSpan(1),
                        ]),
                    ]),
                Section::make('Informasi Program Kerja')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nama Program Kerja')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Select::make('unit_kerja_id')->label('Unit Kerja')->relationship('unitKerja', 'name')->searchable()->preload()->required()->columnSpan(1),
                            Select::make('bidang_id')->label('Bidang')->relationship('bidang', 'name')->searchable()->preload()->required()->columnSpan(1),
                            Select::make('kategori_id')->label('Kategori')->relationship('kategori', 'name')->searchable()->preload()->required()->columnSpan(1),
                            Select::make('program_id')->label('Program Induk')->relationship('program', 'name')->searchable()->preload()->required()->columnSpan(1),
                            Select::make('rekening_id')->label('Kode Akun')->relationship('rekening', 'code')->searchable()->preload()->columnSpan(1),
                            TextInput::make('target')->label('Target')->columnSpan(1),
                            TextInput::make('nilai_standar')->label('Nilai Standar')->columnSpan(1),
                            TextInput::make('satuan_nilai_standar')->label('Satuan Nilai Standar')->columnSpan(1),
                            Toggle::make('is_active')->label('Status Aktif')->default(true)->columnSpanFull(),
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
            ]);
    }

    /**
     * Target penawaran diambil dari target acuan pada tahun yang sama dengan tahun
     * mulai Tahun Kerja yang dipilih.
     */
    protected static function syncTargetFromAcuan(?string $acuanId, ?string $tahunKerjaId, Set $set): void
    {
        if ($acuanId === null || $tahunKerjaId === null) {
            return;
        }

        $tahun = TahunKerja::find($tahunKerjaId)?->tahunTarget();

        if ($tahun === null) {
            return;
        }

        $target = AcuanTarget::query()
            ->where('acuan_program_kerja_id', $acuanId)
            ->where('tahun', $tahun)
            ->first();

        if ($target !== null) {
            $set('target', $target->label());
        }
    }
}
