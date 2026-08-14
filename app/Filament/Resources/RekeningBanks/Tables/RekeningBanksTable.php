<?php

namespace App\Filament\Resources\RekeningBanks\Tables;

use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Actions\AuthorizedViewAction;
use App\Filament\Actions\CaptchaDeleteAction;
use App\Filament\Actions\CaptchaDeleteBulkAction;
use App\Services\UnitKerjaAktif;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RekeningBanksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada rekening bank')
            ->emptyStateDescription('Klik tombol tambah di kanan atas untuk mendaftarkan rekening tujuan pencairan.')
            ->emptyStateIcon('heroicon-o-credit-card')
            ->columns([
                TextColumn::make('bank.name')
                    ->label('Nama Bank')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nomor_rekening')
                    ->label('Nomor Rekening')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('atas_nama')
                    ->label('Atas Nama')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('unitKerja.name')
                    ->label('Unit Kerja')
                    ->placeholder('Umum')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_utama')
                    ->label('Utama')
                    ->boolean(),
                ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('bank_id')->label('Bank')->relationship('bank', 'name')->searchable()->preload(),
                SelectFilter::make('unit_kerja_id')
                    ->label('Unit Kerja')
                    ->relationship('unitKerja', 'name', fn (Builder $query): Builder => UnitKerjaAktif::batasiKueri($query))
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_utama')->label('Rekening Utama'),
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
