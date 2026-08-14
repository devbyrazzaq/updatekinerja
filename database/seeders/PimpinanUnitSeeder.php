<?php

namespace Database\Seeders;

use App\Enums\EnumJenisKelamin;
use App\Enums\EnumRole;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Satu akun Pimpinan Unit untuk tiap unit kerja aktif.
 *
 * Tiap akun memegang role fungsional "Pimpinan Unit", role pembatas data unitnya
 * sendiri (mis. "Unit: Perpustakaan", disemai UnitKerjaScopeRoleSeeder), plus role
 * utama "Dosen" supaya akunnya terkelola lewat menu Dosen pada grup "Pengguna".
 *
 * Usernamenya berupa 8 digit angka yang diturunkan dari slug unit kerja
 * ({@see self::usernames()}), sehingga tetap sama tiap kali seeder diulang dan bisa
 * dihitung ulang oleh `php artisan seed:hapus-pimpinan-unit` saat perlu menghapus
 * akun-akun ini tanpa menyentuh data lain.
 */
class PimpinanUnitSeeder extends Seeder
{
    /**
     * Kata sandi seragam untuk seluruh akun demo.
     */
    public const PASSWORD = 'password';

    public function run(): void
    {
        $usernames = static::usernames();

        foreach (UnitKerja::where('is_active', true)->orderBy('id')->get() as $unitKerja) {
            $username = $usernames[$unitKerja->id];

            $user = User::updateOrCreate(
                ['username' => $username],
                [
                    'name' => 'Pimpinan '.$unitKerja->name,
                    'email' => $username.'@umla.ac.id',
                    'password' => Hash::make(static::PASSWORD),
                    'gender' => EnumJenisKelamin::LakiLaki,
                    'birth_date' => '2000-01-01',
                    'unit_kerja_id' => $unitKerja->id,
                    'is_active' => true,
                ],
            );

            $user->syncRoles([
                EnumRole::Dosen->value,
                EnumRole::PimpinanUnit->value,
                EnumRole::unitScopeName($unitKerja->name),
            ]);
        }
    }

    /**
     * Username 8 digit tiap unit kerja, berbentuk `[unit_kerja_id => username]`.
     *
     * Angkanya diturunkan dari slug unit (crc32) supaya stabil — unit yang sama
     * selalu menghasilkan username yang sama walau daftar unit bertambah. Seluruh
     * unit ikut dihitung, termasuk yang nonaktif, agar username unit yang sudah
     * telanjur disemai tidak bergeser saat statusnya berubah.
     *
     * @return array<int, string>
     */
    public static function usernames(): array
    {
        $usernames = [];

        foreach (UnitKerja::orderBy('id')->get() as $unitKerja) {
            $nomor = 10_000_000 + (crc32($unitKerja->slug) % 90_000_000);

            // Dua slug berbeda bisa jatuh ke angka yang sama; geser ke angka bebas
            // berikutnya. Urutan id yang tetap membuat hasilnya tetap deterministik.
            while (in_array((string) $nomor, $usernames, true)) {
                $nomor = $nomor < 99_999_999 ? $nomor + 1 : 10_000_000;
            }

            $usernames[$unitKerja->id] = (string) $nomor;
        }

        return $usernames;
    }
}
