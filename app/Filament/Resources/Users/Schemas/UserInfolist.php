<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(4)
                    ->schema([
                        Section::make()
                            ->schema([
                                ImageEntry::make('avatar_url')
                                    ->hiddenLabel()
                                    ->alignCenter()
                                    ->circular()
                                    ->defaultImageUrl(fn (User $record): string => $record->getFilamentAvatarUrl()),
                                TextEntry::make('fullname')
                                    ->hiddenLabel()
                                    ->alignCenter()
                                    ->state(fn (User $record): string => $record->getFullName()),
                            ])
                            ->columnSpan(1),
                        Section::make('Informasi Akun')
                            ->schema([
                                TextEntry::make('username'),
                                TextEntry::make('email')
                                    ->label('Alamat Email')
                                    ->placeholder('-'),
                                TextEntry::make('birth_date')
                                    ->label('Tanggal Lahir')
                                    ->date('d F Y')
                                    ->placeholder('-'),
                                TextEntry::make('age')
                                    ->label('Umur')
                                    ->suffix(' Tahun')
                                    ->state(fn (User $record): ?int => $record->getAge())
                                    ->placeholder('-'),
                                TextEntry::make('gender')
                                    ->label('Jenis Kelamin')
                                    ->badge()
                                    ->placeholder('-'),
                                TextEntry::make('phone')
                                    ->label('Nomor Telepon')
                                    ->placeholder('Belum tersedia'),
                                TextEntry::make('email_verified_at')
                                    ->label('Email Terverifikasi Pada')
                                    ->dateTime('d F Y H:i')
                                    ->placeholder('Belum terverifikasi'),
                                TextEntry::make('is_active')
                                    ->label('Status Aktif')
                                    ->badge()
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Tidak Aktif')
                                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                                TextEntry::make('created_at')
                                    ->label('Data Dibuat Pada')
                                    ->dateTime('d F Y H:i')
                                    ->placeholder('-'),
                                TextEntry::make('updated_at')
                                    ->label('Data Diperbarui Pada')
                                    ->dateTime('d F Y H:i')
                                    ->placeholder('-'),
                            ])
                            ->columns(2)
                            ->columnSpan(3),
                    ])
                    ->columnSpanFull(),
                Section::make('Penempatan dan Hak Akses')
                    ->schema([
                        TextEntry::make('unitKerja.name')
                            ->label('Unit Kerja')
                            ->placeholder('-'),
                        TextEntry::make('roles.name')
                            ->label('Role')
                            ->badge()
                            ->placeholder('-'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
