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
use Illuminate\Database\Eloquent\Model;
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
     * Role utama yang menjadi isi menu ini. Umumnya satu role ({@see static::$personaRole});
     * menu yang memuat beberapa persona sekaligus (mis. Administrator) meng-override
     * method ini.
     *
     * @return array<int, string>
     */
    public static function personaRoles(): array
    {
        return static::$personaRole === null ? [] : [static::$personaRole];
    }

    /**
     * Persona yang boleh dipilih pengguna saat ini pada form, sebagai opsi
     * `nama role => label`. Kosong berarti menu ini hanya punya satu persona yang
     * melekat otomatis, sehingga form tidak menampilkan pilihan persona.
     *
     * @return array<string, string>
     */
    public static function pilihanPersona(): array
    {
        return [];
    }

    /**
     * Menu persona hanya memuat akun yang berrole utama tersebut.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (static::personaRoles() !== []) {
            $query->whereHas('roles', fn (Builder $roles): Builder => $roles->whereIn('name', static::personaRoles()));
        }

        return $query;
    }

    /**
     * Akun berakses penuh (mis. Super Admin) hanya boleh dikelola oleh pengguna yang
     * juga berakses penuh, agar pengguna dengan hak akses lebih rendah tidak dapat
     * mengambil alih akun tersebut lewat ubah data, username, atau kata sandinya.
     */
    public static function bolehMengelolaAkun(Model $record): bool
    {
        if (! $record instanceof User || ! $record->isPrivileged()) {
            return true;
        }

        $pengguna = auth()->user();

        return $pengguna instanceof User && $pengguna->isPrivileged();
    }

    /**
     * Pengguna saat ini memegang permission `<ability>_<resource>` sekaligus boleh
     * mengelola akun yang dituju. Dipakai aksi akun seperti Ubah Username dan Reset
     * Kata Sandi.
     */
    public static function currentUserCanKelolaAkun(string $ability, User $record): bool
    {
        return static::currentUserCan(static::getPermissionName($ability))
            && static::bolehMengelolaAkun($record);
    }

    public static function canEdit(Model $record): bool
    {
        return static::currentUserCan(static::getPermissionName('update'))
            && static::bolehMengelolaAkun($record);
    }

    /**
     * Akun sendiri tidak dapat dihapus dari menu ini, sehingga selalu tersisa
     * setidaknya satu akun yang dapat mengelola aplikasi.
     */
    public static function canDelete(Model $record): bool
    {
        return static::currentUserCan(static::getPermissionName('delete'))
            && static::bolehMengelolaAkun($record)
            && ! $record->is(auth()->user());
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
            "impersonate_{$prefix}" => 'Masuk Sebagai Pengguna',
            "update_username_{$prefix}" => 'Ubah Username',
            "reset_password_{$prefix}" => 'Reset Kata Sandi',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema, static::personaRoles(), static::pilihanPersona());
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
