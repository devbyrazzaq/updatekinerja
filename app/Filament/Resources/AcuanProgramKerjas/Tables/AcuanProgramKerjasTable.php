<?php

namespace App\Filament\Resources\AcuanProgramKerjas\Tables;

use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Actions\AuthorizedViewAction;
use App\Filament\Actions\CaptchaDeleteAction;
use App\Models\AcuanProgramKerja;
use App\Models\KelompokAcuan;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AcuanProgramKerjasTable
{
    /**
     * Satu kolom target untuk tiap tahun yang dicakup kelompok acuan. Jumlah tahun
     * mengikuti pengaturan sistem "jumlah tahun dalam 1 periode jabatan".
     *
     * @return array<int, TextColumn>
     */
    public static function tahunColumns(?KelompokAcuan $kelompokAcuan): array
    {
        return array_map(
            fn (int $tahun): TextColumn => TextColumn::make("target_{$tahun}")
                ->label((string) $tahun)
                ->state(fn (AcuanProgramKerja $record): ?string => $record->targetTahun($tahun))
                ->badge()
                ->color('gray')
                ->placeholder('-')
                ->alignCenter(),
            $kelompokAcuan?->tahunList() ?? [],
        );
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada acuan program kerja')
            ->emptyStateDescription('Klik tombol tambah di kanan atas untuk menambahkan acuan program kerja baru.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->columns([
                TextColumn::make('unitKerja.name')
                    ->label('Unit Kerja')
                    ->searchable()
                    ->extraHeaderAttributes(['class' => 'kolom-lekat'])
                    ->extraCellAttributes(['class' => 'kolom-lekat']),
                TextColumn::make('name')
                    ->label('Nama Program Kerja')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('bidang.name')
                    ->label('Bidang')
                    ->toggleable(),
                TextColumn::make('kategori.name')
                    ->label('Kategori')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('program.name')
                    ->label('Program Induk')
                    ->toggleable(),
                ...static::tahunColumns(KelompokAcuan::active()),
                ToggleColumn::make('is_active')
                    ->label('Aktif'),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d F Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('unit_kerja_id')->label('Unit Kerja')->relationship('unitKerja', 'name')->searchable()->preload(),
                SelectFilter::make('bidang_id')->label('Bidang')->relationship('bidang', 'name')->searchable()->preload(),
                SelectFilter::make('kategori_id')->label('Kategori')->relationship('kategori', 'name')->searchable()->preload(),
                SelectFilter::make('program_id')->label('Program Induk')->relationship('program', 'name')->searchable()->preload(),
                TernaryFilter::make('is_active')->label('Status Aktif'),
            ])
            ->recordActions([
                ActionGroup::make([
                    AuthorizedViewAction::make()->label('Lihat'),
                    AuthorizedEditAction::make()->label('Ubah'),
                    CaptchaDeleteAction::make(),
                ]),
            ])
            ->toolbarActions([]);
    }
}
