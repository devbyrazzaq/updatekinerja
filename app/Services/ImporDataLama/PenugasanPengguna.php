<?php

namespace App\Services\ImporDataLama;

use App\Enums\EnumRole;
use App\Models\UnitKerja;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Menerapkan konsep penugasan LAMADU pada akun sistem ini.
 *
 * Di LAMADU, peran seseorang tercatat di `multi_roles` (boleh lebih dari satu) dan
 * unit kerja yang dipegangnya tercatat dua lapis: `assignments` untuk unit utama dan
 * `list_of_assignments` untuk unit tambahan yang juga boleh ia kelola. Padanannya di
 * sini: role fungsional, kolom `unit_kerja_id` untuk unit utama, dan role pembatas data
 * "Unit: ..." untuk setiap unit yang ditugaskan (lihat {@see EnumRole::unitScopeName()}).
 *
 * Penerapannya menyelaraskan, bukan sekadar menambah: role fungsional dan role pembatas
 * data yang tidak lagi ada di LAMADU dicabut. Role persona (Dosen, Tenaga Pendidik, Admin)
 * tidak tersentuh karena tidak berasal dari penugasan — kecuali akun yang belum punya
 * persona sama sekali (umumnya akun yang dibuat oleh impor), yang diberi persona
 * bawaan agar akunnya tampil di menu Pengguna.
 */
class PenugasanPengguna
{
    /**
     * Role persona yang menentukan menu tempat akun dikelola, bukan hasil penugasan.
     *
     * @var array<int, EnumRole>
     */
    private const ROLE_PERSONA = [EnumRole::Dosen, EnumRole::TenagaPendidik, EnumRole::Admin];

    /**
     * @param  array<int, string>  $roles  role fungsional sistem ini
     * @param  array<int, int>  $unitKerjaIds  seluruh unit yang ditugaskan, termasuk unit utama
     * @param  EnumRole|null  $personaBawaan  persona bagi akun yang belum punya persona
     */
    public function terapkan(User $user, array $roles, ?int $unitUtamaId, array $unitKerjaIds, ?EnumRole $personaBawaan = null): void
    {
        $unitKerjas = UnitKerja::query()
            ->whereIn('id', array_filter([$unitUtamaId, ...$unitKerjaIds]))
            ->pluck('name', 'id');

        $rolePembatas = $unitKerjas
            ->map(fn (string $nama): string => Role::findOrCreate(EnumRole::unitScopeName($nama), 'web')->name)
            ->values()
            ->all();

        $rolePersona = $user->getRoleNames()
            ->intersect(array_map(fn (EnumRole $role): string => $role->value, self::ROLE_PERSONA))
            ->all();

        if ($rolePersona === [] && $personaBawaan !== null) {
            $rolePersona = [$personaBawaan->value];
        }

        $user->syncRoles(array_values(array_unique([...$rolePersona, ...$roles, ...$rolePembatas])));

        if ($unitUtamaId !== null && $unitKerjas->has($unitUtamaId)) {
            $user->forceFill(['unit_kerja_id' => $unitUtamaId])->save();
        }
    }
}
