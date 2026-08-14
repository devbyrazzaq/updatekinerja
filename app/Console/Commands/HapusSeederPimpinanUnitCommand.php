<?php

namespace App\Console\Commands;

use App\Enums\EnumRole;
use App\Models\User;
use Database\Seeders\PimpinanUnitSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Membatalkan PimpinanUnitSeeder.
 *
 * Sasarannya dihitung ulang dari PimpinanUnitSeeder::usernames() — hanya akun yang
 * usernamenya persis milik sebuah unit kerja, ditempatkan pada unit tersebut, dan
 * berrole "Pimpinan Unit" yang dihapus. Role, unit kerja, dan akun lain tidak
 * disentuh; akun yang sudah dipakai sebagai relasi data (mis. pengaju program kerja)
 * dilewati beserta alasannya supaya tak ada data lain yang rusak.
 */
#[Signature('seed:hapus-pimpinan-unit {--force : Hapus tanpa konfirmasi}')]
#[Description('Hapus akun Pimpinan Unit hasil PimpinanUnitSeeder (data lain tidak ikut terhapus)')]
class HapusSeederPimpinanUnitCommand extends Command
{
    public function handle(): int
    {
        $users = $this->akunSeeder();

        if ($users->isEmpty()) {
            $this->components->info('Tidak ada akun Pimpinan Unit hasil seeder yang perlu dihapus.');

            return self::SUCCESS;
        }

        $this->components->bulletList(
            $users->map(fn (User $user): string => "{$user->username} — {$user->name}")->all(),
        );

        if (! $this->option('force') && ! $this->confirm("Hapus {$users->count()} akun di atas?", false)) {
            $this->components->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        $terhapus = 0;
        $dilewati = [];

        foreach ($users as $user) {
            try {
                // Transaksi per akun: bila penghapusan ditolak karena relasi data,
                // pencabutan role oleh Spatie (terjadi sebelum DELETE) ikut dibatalkan.
                DB::transaction(fn () => $user->delete());
                $terhapus++;
            } catch (QueryException) {
                $dilewati[] = $user->username;
                $this->components->warn("{$user->username} dilewati: masih dipakai data lain.");
            }
        }

        $this->components->info("{$terhapus} akun Pimpinan Unit dihapus.");

        if ($dilewati !== []) {
            $this->components->warn(count($dilewati).' akun dilewati: '.implode(', ', $dilewati));
        }

        return self::SUCCESS;
    }

    /**
     * Akun yang benar-benar dibuat oleh PimpinanUnitSeeder.
     *
     * @return Collection<int, User>
     */
    private function akunSeeder(): Collection
    {
        $usernames = PimpinanUnitSeeder::usernames();

        return User::query()
            ->whereIn('username', array_values($usernames))
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->where('name', EnumRole::PimpinanUnit->value))
            ->get()
            // Username dan unit kerja harus berpasangan seperti hasil seeder; akun
            // yang kebetulan bernama sama tapi beda unit bukan bikinan seeder ini.
            ->filter(fn (User $user): bool => ($usernames[$user->unit_kerja_id] ?? null) === $user->username)
            ->sortBy('username')
            ->values();
    }
}
