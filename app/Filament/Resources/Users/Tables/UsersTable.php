<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\EnumJenisKelamin;
use App\Filament\Actions\AuthorizedEditAction;
use App\Filament\Actions\AuthorizedViewAction;
use App\Filament\Actions\CaptchaDeleteAction;
use App\Filament\Actions\CaptchaDeleteBulkAction;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada pengguna')
            ->emptyStateDescription('Klik tombol tambah di kanan atas untuk menambahkan pengguna baru.')
            ->emptyStateIcon('heroicon-o-users')
            ->columns([
                ImageColumn::make('avatar_url')
                    ->label('Foto Profil')
                    ->defaultImageUrl(fn (User $record): string => $record->getDefaultFilamentAvatarUrl())
                    ->circular()
                    ->width(54)
                    ->height(54),
                TextColumn::make('name')
                    ->label('Nama')
                    ->wrap()
                    ->formatStateUsing(fn (User $record): string => $record->getFullName())
                    ->searchable(['name', 'front_title', 'back_title'])
                    ->sortable(),
                TextColumn::make('username')
                    ->label('Username')
                    ->description(fn (User $record): ?string => $record->phone)
                    ->searchable(['username', 'phone']),
                TextColumn::make('gender')
                    ->label('Jenis Kelamin')
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge(),
                TextColumn::make('unitKerja.name')
                    ->label('Unit Kerja')
                    ->toggleable(),
                ToggleColumn::make('is_active')
                    ->label('Aktif'),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d F Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('unit_kerja_id')
                    ->label('Unit Kerja')
                    ->relationship('unitKerja', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('gender')
                    ->label('Jenis Kelamin')
                    ->options(EnumJenisKelamin::class),
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
