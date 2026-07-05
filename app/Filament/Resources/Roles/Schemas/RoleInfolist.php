<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class RoleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Detail Role')
                ->schema([
                    TextEntry::make('name')
                        ->label('Nama Role'),
                    TextEntry::make('guard_name')
                        ->label('Guard')
                        ->badge(),
                    TextEntry::make('permissions_count')
                        ->label('Jumlah Hak Akses')
                        ->state(fn (Role $record): int => $record->permissions_count ?? $record->permissions()->count())
                        ->badge(),
                    TextEntry::make('users_count')
                        ->label('Jumlah Pengguna')
                        ->state(fn (Role $record): int => $record->users_count ?? $record->users()->count())
                        ->badge(),
                    TextEntry::make('created_at')
                        ->label('Dibuat')
                        ->dateTime('d F Y H:i'),
                    TextEntry::make('updated_at')
                        ->label('Diperbarui')
                        ->dateTime('d F Y H:i'),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }
}
