<?php

namespace App\Filament\Resources\Administrators;

use App\Enums\EnumRole;
use App\Filament\Resources\Administrators\Pages\CreateAdministrator;
use App\Filament\Resources\Administrators\Pages\EditAdministrator;
use App\Filament\Resources\Administrators\Pages\ListAdministrators;
use App\Filament\Resources\Administrators\Pages\ViewAdministrator;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Salinan menu Pengguna untuk akun pengelola aplikasi: Admin dan Super Admin. Berbeda
 * dari menu Dosen dan Tenaga Pendidik, menu ini memuat dua persona sekaligus, jadi
 * form menampilkan pilihan Jenis Akun yang menjadi role utamanya.
 *
 * Opsi Super Admin hanya ditawarkan kepada pengguna berakses penuh, dan akun berakses
 * penuh hanya dapat dikelola pengguna berakses penuh (lihat
 * {@see UserResource::bolehMengelolaAkun()}).
 */
class AdministratorResource extends UserResource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $navigationLabel = 'Administrator';

    protected static ?string $modelLabel = 'Administrator';

    protected static ?string $pluralLabel = 'Data Administrator';

    protected static ?int $navigationSort = 0;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Manajemen Akses';
    }

    /**
     * @return array<int, string>
     */
    public static function personaRoles(): array
    {
        return [EnumRole::SuperAdmin->value, EnumRole::Admin->value];
    }

    /**
     * @return array<string, string>
     */
    public static function pilihanPersona(): array
    {
        $pengguna = auth()->user();

        return array_filter([
            EnumRole::Admin->value => EnumRole::Admin->value,
            EnumRole::SuperAdmin->value => $pengguna instanceof User && $pengguna->isPrivileged()
                ? EnumRole::SuperAdmin->value
                : null,
        ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListAdministrators::route('/'),
            'create' => CreateAdministrator::route('/create'),
            'view' => ViewAdministrator::route('/{record}'),
            'edit' => EditAdministrator::route('/{record}/edit'),
        ];
    }
}
