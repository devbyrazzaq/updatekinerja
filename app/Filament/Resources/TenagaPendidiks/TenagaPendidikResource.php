<?php

namespace App\Filament\Resources\TenagaPendidiks;

use App\Enums\EnumRole;
use App\Filament\Resources\TenagaPendidiks\Pages\CreateTenagaPendidik;
use App\Filament\Resources\TenagaPendidiks\Pages\EditTenagaPendidik;
use App\Filament\Resources\TenagaPendidiks\Pages\ListTenagaPendidiks;
use App\Filament\Resources\TenagaPendidiks\Pages\ViewTenagaPendidik;
use App\Filament\Resources\Users\UserResource;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Support\Icons\Heroicon;

/**
 * Salinan menu Pengguna yang khusus memuat akun berrole utama Tenaga Pendidik.
 * Lihat DosenResource untuk penjelasan polanya.
 */
class TenagaPendidikResource extends UserResource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $navigationLabel = 'Tenaga Pendidik';

    protected static ?string $modelLabel = 'Tenaga Pendidik';

    protected static ?string $pluralLabel = 'Data Tenaga Pendidik';

    protected static ?int $navigationSort = 2;

    public static ?string $personaRole = EnumRole::TenagaPendidik->value;

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListTenagaPendidiks::route('/'),
            'create' => CreateTenagaPendidik::route('/create'),
            'view' => ViewTenagaPendidik::route('/{record}'),
            'edit' => EditTenagaPendidik::route('/{record}/edit'),
        ];
    }
}
