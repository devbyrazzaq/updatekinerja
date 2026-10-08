<?php

namespace App\Enums;

/**
 * Single source of truth untuk nama role yang tersimpan di database.
 *
 * Gunakan enum ini setiap kali perlu menyebut nama role (assignRole, syncRoles,
 * query ke tabel roles, dll). Untuk pengecekan otorisasi, lebih baik gunakan
 * permission (mis. $user->can('bypass_data_scope')) agar tidak bergantung pada
 * nama role.
 */
enum EnumRole: string
{
    // WAJIB: role admin tertinggi yang bisa mengakses semua fitur.
    case SuperAdmin = 'Super Admin';

    // Role utama pengguna (persona). Menentukan menu tempat akun dikelola: Dosen
    // dan Tenaga Pendidik punya layarnya masing-masing pada grup "Pengguna".
    case Dosen = 'Dosen';
    case TenagaPendidik = 'Tenaga Pendidik';

    // Role fungsional alur manajemen kinerja.
    case Admin = 'Admin';
    case UnitKerja = 'Unit Kerja';
    case PimpinanUnit = 'Pimpinan Unit';
    case Rektor = 'Rektor';
    // Wakil Rektor II memegang verifikasi realisasi & pemasukan; Wakil Rektor I dan
    // III hanya memantau, sehingga ketiganya dipisah meski hak aksesnya berdekatan.
    case WakilRektorI = 'Wakil Rektor I';
    case WakilRektorII = 'Wakil Rektor II';
    case WakilRektorIII = 'Wakil Rektor III';
    case BiroKeuangan = 'Biro Keuangan';
    case VerifikatorLaporan = 'Verifikator Laporan';

    /**
     * Awalan nama role pembatas data per unit kerja. Role bertipe ini tidak memberi
     * hak akses menu apa pun — isinya hanya permission scope `view_data_unit_{id}`,
     * sehingga bisa ditumpuk berapa pun banyaknya pada satu pengguna untuk memberi
     * akses ke lebih dari satu unit kerja (lihat UnitKerjaScopeRoleSeeder).
     */
    public const UNIT_SCOPE_PREFIX = 'Unit: ';

    /**
     * Nama role pembatas data untuk sebuah unit kerja, mis. "Unit: Perpustakaan".
     */
    public static function unitScopeName(string $unitKerjaName): string
    {
        return self::UNIT_SCOPE_PREFIX.$unitKerjaName;
    }

    /**
     * Apakah sebuah nama role merupakan role pembatas data per unit kerja.
     */
    public static function isUnitScopeName(string $roleName): bool
    {
        return str_starts_with($roleName, self::UNIT_SCOPE_PREFIX);
    }

    /**
     * Nama seluruh role fungsional (di luar role pembatas data per unit kerja).
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'value');
    }
}
