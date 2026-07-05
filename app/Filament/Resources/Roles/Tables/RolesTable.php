<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Actions\AuthorizedViewAction;
use App\Filament\Actions\CaptchaDeleteBulkAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada role')
            ->emptyStateDescription('Klik tombol tambah di kanan atas untuk menambahkan role baru.')
            ->emptyStateIcon('heroicon-o-shield-check')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Role')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('guard_name')
                    ->label('Guard')
                    ->badge()
                    ->sortable(),
                TextColumn::make('permissions_count')
                    ->label('Hak Akses')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (int|string|null $state): string => ($state ?? 0).' permission'),
                TextColumn::make('users_count')
                    ->label('Pengguna')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (int|string|null $state): string => ($state ?? 0).' pengguna'),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d F Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                ActionGroup::make([
                    AuthorizedViewAction::make()->label('Lihat'),
                    AuthorizedEditAction::make()->label('Ubah'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    CaptchaDeleteBulkAction::make(),
                ]),
            ]);
    }
}
