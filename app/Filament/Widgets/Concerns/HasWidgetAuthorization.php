<?php

namespace App\Filament\Widgets\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

trait HasWidgetAuthorization
{
    /**
     * @return array<string, string>
     */
    public static function getPermissionDefinitions(): array
    {
        return [
            static::getWidgetPermission() => 'Lihat Widget',
        ];
    }

    public static function getPermissionHeading(): string
    {
        return str(class_basename(static::class))->beforeLast('Widget')->headline()->toString();
    }

    public static function getPermissionDescription(): string
    {
        return 'Hak akses untuk melihat widget '.str(static::getPermissionHeading())->lower()->toString().'.';
    }

    /**
     * Deskripsi per permission (nama => teks) untuk kolom "Deskripsi" di tabel form role.
     *
     * @return array<string, string>
     */
    public static function getPermissionDescriptions(): array
    {
        return [
            static::getWidgetPermission() => 'Menampilkan widget '.str(static::getPermissionHeading())->lower()->toString().' pada dashboard.',
        ];
    }

    protected static function getWidgetPermission(): string
    {
        return 'view_widget_'.str(class_basename(static::class))
            ->beforeLast('Widget')
            ->snake()
            ->toString();
    }

    public static function canView(): bool
    {
        return static::currentUserCanViewWidget();
    }

    protected static function currentUserCanViewWidget(): bool
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
            return $user->hasPermissionTo(static::getWidgetPermission());
        } catch (PermissionDoesNotExist) {
            return app()->runningUnitTests();
        }
    }
}
