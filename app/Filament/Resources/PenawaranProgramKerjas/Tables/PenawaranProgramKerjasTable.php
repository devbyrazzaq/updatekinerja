<?php

namespace App\Filament\Resources\PenawaranProgramKerjas\Tables;

use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Actions\AuthorizedViewAction;
use App\Filament\Actions\CaptchaDeleteAction;
use App\Filament\Actions\CaptchaDeleteBulkAction;
use App\Models\AcuanTarget;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PenawaranProgramKerjasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada penawaran program kerja')
            ->emptyStateDescription('Klik tombol tambah di kanan atas untuk menambahkan penawaran program kerja baru.')
            ->emptyStateIcon('heroicon-o-document-check')
            ->columns([
                TextColumn::make('unitKerja.name')
                    ->label('Unit Kerja')
                    ->searchable()
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'kolom-lekat'])
                    ->extraCellAttributes(['class' => 'kolom-lekat']),
                TextColumn::make('name')
                    ->label('Nama Program Kerja')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('acuanProgramKerja.name')
                    ->label('Acuan Program Kerja')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('tahunKerja.name')
                    ->label('Tahun Kerja')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('bidang.name')
                    ->label('Bidang')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('kategori.name')
                    ->label('Kategori')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('program.name')
                    ->label('Program Induk')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('rekening.code')
                    ->label('Kode Akun')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('target')
                    ->label('Target')
                    ->formatStateUsing(fn (?string $state): ?string => $state !== null ? AcuanTarget::formatNilai($state) : null)
                    ->toggleable(),
                TextColumn::make('nilai_standar')
                    ->label('Nilai Standar')
                    ->formatStateUsing(fn (?string $state): ?string => $state !== null ? AcuanTarget::formatNilai($state) : null)
                    ->toggleable(),
                TextColumn::make('satuan_nilai_standar')
                    ->label('Satuan Nilai Standar')
                    ->toggleable(),
                TextColumn::make('aktifitas')
                    ->label('Aktivitas')
                    ->formatStateUsing(fn (?string $state): ?string => $state !== null ? trim(strip_tags($state)) : null)
                    ->wrap()
                    ->limit(80)
                    ->toggleable(),
                TextColumn::make('indikator')
                    ->label('Indikator')
                    ->formatStateUsing(fn (?string $state): ?string => $state !== null ? trim(strip_tags($state)) : null)
                    ->wrap()
                    ->limit(80)
                    ->toggleable(),
                ToggleColumn::make('is_active')
                    ->label('Aktif'),
            ])
            ->filters([
                SelectFilter::make('tahun_kerja_id')->label('Tahun Kerja')->relationship('tahunKerja', 'name')->searchable()->preload(),
                SelectFilter::make('unit_kerja_id')->label('Unit Kerja')->relationship('unitKerja', 'name')->searchable()->preload(),
                SelectFilter::make('kategori_id')->label('Kategori')->relationship('kategori', 'name')->searchable()->preload(),
                TernaryFilter::make('is_active')->label('Status Aktif'),
            ])
            ->recordActions([
                ActionGroup::make([
                    AuthorizedViewAction::make()->label('Lihat'),
                    AuthorizedEditAction::make()->label('Ubah'),
                    CaptchaDeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    CaptchaDeleteBulkAction::make(),
                ]),
            ]);
    }
}
