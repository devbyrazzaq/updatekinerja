<?php

namespace App\Filament\Resources\Banks\Tables;

use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Actions\AuthorizedViewAction;
use App\Filament\Actions\CaptchaDeleteAction;
use App\Filament\Actions\CaptchaDeleteBulkAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BanksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->emptyStateHeading('Belum ada bank')
            ->emptyStateDescription('Klik tombol tambah di kanan atas untuk menambahkan bank baru.')
            ->emptyStateIcon('heroicon-o-building-library')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Bank')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Kode Bank')
                    ->badge()
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rekening_banks_count')
                    ->label('Rekening')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Aktif')
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
