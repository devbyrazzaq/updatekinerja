<?php

namespace App\Filament\Resources\KelompokAcuans\Tables;

use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Actions\AuthorizedViewAction;
use App\Filament\Actions\CaptchaDeleteAction;
use App\Filament\Actions\CaptchaDeleteBulkAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class KelompokAcuansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('tahun_mulai', 'desc')
            ->emptyStateHeading('Belum ada kelompok acuan')
            ->emptyStateDescription('Klik tombol tambah di kanan atas untuk membuat kelompok acuan program kerja baru.')
            ->emptyStateIcon('heroicon-o-rectangle-stack')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Kelompok Acuan')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('tahun_mulai')
                    ->label('Tahun Mulai')
                    ->sortable(),
                TextColumn::make('tahun_selesai')
                    ->label('Tahun Selesai')
                    ->sortable(),
                TextColumn::make('acuan_program_kerjas_count')
                    ->label('Jumlah Acuan')
                    ->counts('acuanProgramKerjas')
                    ->badge(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d F Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
