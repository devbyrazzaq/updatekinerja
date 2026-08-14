<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Kerangka bersama menu pengelolaan akun: form, tabel, dan infolistnya dipakai
 * seluruh menu persona pada grup "Pengguna".
 *
 * Kelas ini sengaja `abstract` sehingga tidak ikut terdaftar sebagai menu maupun
 * grup permission tersendiri — akun hanya dikelola lewat menu personanya (Dosen
 * dan Tenaga Pendidik). Turunannya wajib mengisi {@see static::$personaRole}.
 */
abstract class UserResource extends Resource
{
    use HasResourceAuthorization;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $pluralLabel = 'Data Pengguna';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Role utama (persona) yang menjadi isi menu ini, mis. Dosen. Diisi tiap
     * turunan; lihat DosenResource.
     */
    public static ?string $personaRole = null;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Pengguna';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    /**
     * Menu persona hanya memuat akun yang berrole utama tersebut.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (static::$personaRole !== null) {
            $query->whereHas('roles', fn (Builder $roles): Builder => $roles->where('name', static::$personaRole));
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    public static function getExtraPermissionDefinitions(): array
    {
        $prefix = static::getPermissionPrefix();

        return [
            "export_{$prefix}" => 'Ekspor Data',
            "report_{$prefix}" => 'Unduh Laporan PDF',
            "import_{$prefix}" => 'Impor Data',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema, static::$personaRole);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [];
    }
}
