<?php

use Database\Seeders\RoleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role "Wakil Rektor" dipecah menjadi Wakil Rektor I, II, dan III karena hanya
 * Wakil Rektor II yang memegang verifikasi. Role lama diganti nama menjadi
 * "Wakil Rektor II" sehingga pemegangnya tetap menerima antrean verifikasinya;
 * pemegang yang sebenarnya Wakil Rektor I/III dipindahkan manual lewat menu Pengguna.
 *
 * Setelah itu hak akses Rektor, Wakil Rektor, Biro Keuangan, dan Pimpinan Unit
 * diterapkan ulang dari RoleSeeder supaya tidak perlu disusun satu per satu lewat
 * menu Role & Akses.
 */
return new class extends Migration
{
    private const NAMA_LAMA = 'Wakil Rektor';

    private const NAMA_BARU = 'Wakil Rektor II';

    public function up(): void
    {
        $this->gantiNamaRole(self::NAMA_LAMA, self::NAMA_BARU);

        // Instalasi baru belum punya role apa pun; hak aksesnya disusun saat
        // `db:seed` dijalankan.
        if (DB::table('roles')->exists()) {
            Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Hanya nama role yang dikembalikan; hak akses yang sudah diterapkan dibiarkan
     * karena susunan sebelumnya tidak tersimpan.
     */
    public function down(): void
    {
        $this->gantiNamaRole(self::NAMA_BARU, self::NAMA_LAMA);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function gantiNamaRole(string $dari, string $menjadi): void
    {
        $sudahAda = DB::table('roles')
            ->where('name', $menjadi)
            ->where('guard_name', 'web')
            ->exists();

        if ($sudahAda) {
            return;
        }

        DB::table('roles')
            ->where('name', $dari)
            ->where('guard_name', 'web')
            ->update(['name' => $menjadi, 'updated_at' => now()]);
    }
};
