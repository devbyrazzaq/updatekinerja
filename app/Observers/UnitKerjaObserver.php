<?php

namespace App\Observers;

use App\Enums\EnumRole;
use App\Models\UnitKerja;
use App\Services\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar as SpatiePermissionRegistrar;

/**
 * Menjaga role pembatas data "Unit: ..." tetap sejalan dengan daftar unit kerja:
 * unit baru langsung punya rolenya sendiri, dan unit yang berganti nama ikut
 * memperbarui nama rolenya sehingga tidak menyisakan role yatim di layar Role.
 */
class UnitKerjaObserver
{
    public function created(UnitKerja $unitKerja): void
    {
        $this->syncRole($unitKerja);
    }

    public function updated(UnitKerja $unitKerja): void
    {
        $namaLama = $unitKerja->getOriginal('name');

        if ($namaLama !== null && $namaLama !== $unitKerja->name) {
            Role::where('name', EnumRole::unitScopeName($namaLama))
                ->where('guard_name', 'web')
                ->update(['name' => EnumRole::unitScopeName($unitKerja->name)]);
        }

        $this->syncRole($unitKerja);
    }

    /**
     * Menghapus unit kerja ikut menghapus role pembatasnya; permission scope-nya
     * sudah tidak menunjuk ke record mana pun.
     */
    public function deleted(UnitKerja $unitKerja): void
    {
        Role::where('name', EnumRole::unitScopeName($unitKerja->name))
            ->where('guard_name', 'web')
            ->delete();

        Permission::where('name', PermissionRegistrar::dataScopePermissionPrefix('unit').$unitKerja->id)
            ->where('guard_name', 'web')
            ->delete();

        app(SpatiePermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function syncRole(UnitKerja $unitKerja): void
    {
        $permission = Permission::findOrCreate(
            PermissionRegistrar::dataScopePermissionPrefix('unit').$unitKerja->id,
            'web',
        );

        Role::findOrCreate(EnumRole::unitScopeName($unitKerja->name), 'web')
            ->syncPermissions([$permission]);

        app(SpatiePermissionRegistrar::class)->forgetCachedPermissions();
    }
}
