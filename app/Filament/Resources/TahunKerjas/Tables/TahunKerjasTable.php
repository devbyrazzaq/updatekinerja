<?php

namespace App\Filament\Resources\TahunKerjas\Tables;

use App\Enums\EnumStatusTahunKerja;
use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Actions\AuthorizedViewAction;
use App\Filament\Actions\CaptchaDeleteAction;
use App\Filament\Actions\CaptchaDeleteBulkAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TahunKerjasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada tahun kerja')
            ->emptyStateDescription('Klik tombol tambah di kanan atas untuk menambahkan tahun kerja baru.')
            ->emptyStateIcon('heroicon-o-calendar')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Tahun Kerja')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tahun')
                    ->label('Tahun')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('periode.name')
                    ->label('Periode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_datetime')
                    ->label('Mulai')
                    ->dateTime('d M Y')
                    ->sortable(),
                TextColumn::make('end_datetime')
                    ->label('Selesai')
                    ->dateTime('d M Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('ditutup_pada')
                    ->label('Diakhiri')
                    ->dateTime('d F Y H:i')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('dikunci_pada')
                    ->label('Dikunci')
                    ->dateTime('d F Y H:i')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d F Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('periode_id')
                    ->label('Periode')
                    ->relationship('periode', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(EnumStatusTahunKerja::class),
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
