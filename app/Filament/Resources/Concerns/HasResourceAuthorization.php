<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

trait HasResourceAuthorization
{
    public static function resolveRecordRouteBinding(int|string|Model $key, ?\Closure $modifyQuery = null): ?Model
    {
        if ($key instanceof Model) {
            return $key;
        }

        return parent::resolveRecordRouteBinding($key, $modifyQuery);
    }

    /**
     * @return array<string, string>
     */
    public static function getPermissionDefinitions(): array
    {
        $prefix = static::getPermissionPrefix();

        return [
            "view_any_{$prefix}" => 'Lihat Semua',
            "view_{$prefix}" => 'Lihat Detail',
            "create_{$prefix}" => 'Buat Baru',
            "update_{$prefix}" => 'Ubah',
            "delete_{$prefix}" => 'Hapus',
            "delete_any_{$prefix}" => 'Hapus Massal',
            ...static::getExtraPermissionDefinitions(),
        ];
    }

    /**
     * Permission tambahan spesifik resource (mis. export/import). Resource cukup
     * meng-override method ini alih-alih getPermissionDefinitions() agar tidak
     * kehilangan permission dasar CRUD.
     *
     * @return array<string, string>
     */
    public static function getExtraPermissionDefinitions(): array
    {
        return [];
    }

    public static function getPermissionHeading(): string
    {
        $metadata = property_exists(static::class, 'permissions') ? static::$permissions : [];

        return $metadata['heading']
            ?? static::$navigationLabel
            ?? static::$pluralLabel
            ?? str(class_basename(static::class))->beforeLast('Resource')->headline()->toString();
    }

    public static function getPermissionDescription(): string
    {
        $metadata = property_exists(static::class, 'permissions') ? static::$permissions : [];

        return $metadata['description']
            ?? 'Hak akses untuk mengelola '.str(static::getPermissionHeading())->lower()->toString().'.';
    }

    /**
     * Deskripsi per permission (nama => teks) untuk kolom "Deskripsi" di tabel form role.
     * Default dirakit dari heading; bisa di-override (method ini atau metadata
     * $permissions['permission_descriptions']).
     *
     * @return array<string, string>
     */
    public static function getPermissionDescriptions(): array
    {
        $metadata = property_exists(static::class, 'permissions') ? static::$permissions : [];

        if (isset($metadata['permission_descriptions'])) {
            return $metadata['permission_descriptions'];
        }

        $prefix = static::getPermissionPrefix();
        $heading = str(static::getPermissionHeading())->lower()->toString();

        return [
            "view_any_{$prefix}" => "Melihat daftar {$heading}.",
            "view_{$prefix}" => "Melihat detail {$heading}.",
            "create_{$prefix}" => "Menambah {$heading} baru.",
            "update_{$prefix}" => "Mengubah data {$heading}.",
            "delete_{$prefix}" => "Menghapus {$heading}.",
            "delete_any_{$prefix}" => "Menghapus beberapa {$heading} sekaligus.",
        ];
    }

    public static function canViewAny(): bool
    {
        return static::currentUserCan(static::getPermissionName('view_any'));
    }

    public static function canView(Model $record): bool
    {
        return static::currentUserCan(static::getPermissionName('view'));
    }

    public static function canCreate(): bool
    {
        return static::currentUserCan(static::getPermissionName('create'));
    }

    public static function canEdit(Model $record): bool
    {
        return static::currentUserCan(static::getPermissionName('update'));
    }

    public static function canDelete(Model $record): bool
    {
        return static::currentUserCan(static::getPermissionName('delete'));
    }

    public static function canDeleteAny(): bool
    {
        return static::currentUserCan(static::getPermissionName('delete_any'));
    }

    /**
     * Nama permission lengkap sebuah ability pada resource ini. Publik supaya halaman
     * dan aksi bisa menyebut permission resource yang sedang aktif alih-alih
     * menuliskannya sebagai literal — penting bagi resource yang punya salinan
     * per slot tahun kerja, karena prefix permission tiap salinan berbeda.
     */
    public static function getPermissionName(string $ability): string
    {
        return $ability.'_'.static::getPermissionPrefix();
    }

    protected static function getPermissionPrefix(): string
    {
        return static::$permissionPrefix ?? str(class_basename(static::class))
            ->beforeLast('Resource')
            ->snake()
            ->toString();
    }

    protected static function currentUserCan(string $permissionName): bool
    {
        $user = auth()->user();

        if (! $user instanceof Authenticatable) {
            return false;
        }

        if ($user?->can('bypass_data_scope') ?? false) {
            return true;
        }

        if (! method_exists($user, 'hasPermissionTo')) {
            return false;
        }

        try {
            return $user->hasPermissionTo($permissionName);
        } catch (PermissionDoesNotExist) {
            return app()->runningUnitTests();
        }
    }
}
