<?php

namespace Database\Seeders;

use App\Enums\EnumRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Akun Dosen hasil pemindahan dari aplikasi LAMADU (tabel `users` yang berrole Dosen).
 *
 * Datanya sudah diekstraksi ke `database/data/dosen-lamadu.json` supaya seeder ini
 * tidak bergantung pada dump SQL LAMADU yang berada di luar repositori. Hash password
 * ikut dibawa apa adanya sehingga dosen dapat login memakai kata sandi lamanya.
 */
class DosenSeeder extends Seeder
{
    public const DATA_PATH = 'data/dosen-lamadu.json';

    public function run(): void
    {
        foreach ($this->dosen() as $dosen) {
            $user = User::firstOrNew(['username' => $dosen['username']]);

            $user->fill([
                'name' => $dosen['name'],
                'email' => $dosen['email'],
                'front_title' => $dosen['front_title'],
                'back_title' => $dosen['back_title'],
                'phone' => $dosen['phone'],
                'birth_date' => $dosen['birth_date'],
                'gender' => $dosen['gender'],
                'is_active' => $dosen['is_active'],
            ]);

            // Hash dari LAMADU dipertahankan apa adanya supaya kata sandi lama dosen
            // tetap berlaku, termasuk bila cost bcrypt-nya berbeda dari aplikasi ini.
            $user->setHashedPassword($dosen['password']);
            $user->save();

            $user->assignRole(EnumRole::Dosen->value);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dosen(): array
    {
        $path = database_path(static::DATA_PATH);

        if (! is_file($path)) {
            throw new RuntimeException("Berkas data dosen tidak ditemukan: {$path}");
        }

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }
}
