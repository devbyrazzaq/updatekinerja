<?php

namespace App\Filament\Pages\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

trait HasPageAuthorization
{
    /**
     * @return array<string, string>
     */
    public static function getPermissionDefinitions(): array
    {
        return [
            static::getPagePermission() => 'Akses Halaman',
        ];
    }

    public static function getPermissionHeading(): string
    {
        $metadata = property_exists(static::class, 'permissions') ? static::$permissions : [];

        $navigationLabel = property_exists(static::class, 'navigationLabel') ? static::$navigationLabel : null;
        $title = property_exists(static::class, 'title') ? static::$title : null;

        return $metadata['heading']
            ?? $navigationLabel
            ?? $title
            ?? str(class_basename(static::class))->beforeLast('Page')->headline()->toString();
    }

    public static function getPermissionDescription(): string
    {
        $metadata = property_exists(static::class, 'permissions') ? static::$permissions : [];

        return $metadata['description']
            ?? 'Hak akses untuk membuka halaman '.str(static::getPermissionHeading())->lower()->toString().'.';
    }

    /**
     * Deskripsi per permission (nama => teks) untuk kolom "Deskripsi" di tabel form role.
     *
     * @return array<string, string>
     */
    public static function getPermissionDescriptions(): array
    {
        $metadata = property_exists(static::class, 'permissions') ? static::$permissions : [];

        return $metadata['permission_descriptions']
            ?? [static::getPagePermission() => 'Mengakses halaman '.str(static::getPermissionHeading())->lower()->toString().'.'];
    }

    /**
     * Publik supaya halaman turunan yang menumpang hak akses halaman ini, maupun
     * widgetnya, bisa menyebut permission yang sama.
     */
    public static function getPagePermission(): string
    {
        return 'view_page_'.str(class_basename(static::class))
            ->beforeLast('Page')
            ->snake()
            ->toString();
    }

    public static function canAccess(): bool
    {
        return static::currentUserCanAccessPage();
    }

    protected static function currentUserCanAccessPage(): bool
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
            return $user->hasPermissionTo(static::getPagePermission());
        } catch (PermissionDoesNotExist) {
            return app()->runningUnitTests();
        }
    }
}
