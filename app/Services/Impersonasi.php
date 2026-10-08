<?php

namespace App\Services;

use App\Enums\EnumPermission;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Masuk sebagai pengguna lain ("impersonate") untuk melihat aplikasi persis dari
 * sudut pandangnya — menu, hak akses, dan pembatasan data ikut berganti karena
 * `auth()->user()` benar-benar diganti, bukan sekadar disimulasikan.
 *
 * Id pengguna asli disimpan di session supaya bisa kembali tanpa login ulang. Setiap
 * pergantian akun wajib memperbarui hash password di session: middleware
 * `AuthenticateSession` panel mencocokkannya dengan password pengguna yang sedang
 * login dan langsung me-logout (sekaligus mengosongkan session) bila berbeda.
 */
class Impersonasi
{
    public const SESSION_PENGGUNA_ASLI = 'impersonasi.pengguna_asli_id';

    public const SESSION_URL_KEMBALI = 'impersonasi.url_kembali';

    /** Pilihan unit kerja aktif milik pengguna asli, dipulihkan saat kembali. */
    public const SESSION_UNIT_KERJA_ASLI = 'impersonasi.unit_kerja_aktif';

    /**
     * Aturan target yang boleh dimasuki, di luar permission aksinya: bukan dirinya
     * sendiri, akunnya aktif, tidak sedang dalam mode impersonasi (tanpa bertingkat),
     * dan bukan akun berakses penuh — supaya impersonasi tidak pernah menjadi jalan
     * untuk menaikkan hak akses.
     */
    public static function bolehMasukSebagai(User $target): bool
    {
        $pengguna = Auth::user();

        if (! $pengguna instanceof User || static::aktif()) {
            return false;
        }

        return ! $pengguna->is($target)
            && $target->is_active
            && ! $target->can(EnumPermission::BypassDataScope->value);
    }

    /**
     * Ganti akun yang login menjadi `$target`. `$urlKembali` adalah halaman asal
     * aksi, tujuan pengalihan saat pengguna asli kembali nanti.
     */
    public static function mulai(User $target, string $urlKembali): void
    {
        abort_unless(static::bolehMasukSebagai($target), 403);

        /** @var User $penggunaAsli */
        $penggunaAsli = Auth::user();

        session()->put([
            static::SESSION_PENGGUNA_ASLI => $penggunaAsli->getKey(),
            static::SESSION_URL_KEMBALI => $urlKembali,
            static::SESSION_UNIT_KERJA_ASLI => session(UnitKerjaAktif::SESSION_KEY),
        ]);
        session()->forget(UnitKerjaAktif::SESSION_KEY);

        static::loginSebagai($target);

        Log::info('Impersonasi dimulai.', [
            'pengguna_asli' => $penggunaAsli->username,
            'target' => $target->username,
        ]);
    }

    /**
     * Kembali ke akun asli dan kembalikan URL halaman asal impersonasi. Bila akun
     * asli sudah tidak ada atau dinonaktifkan, sesi diakhiri sepenuhnya dan pengguna
     * diarahkan ke halaman login.
     */
    public static function kembali(): string
    {
        $penggunaAsli = static::penggunaAsli();
        $target = Auth::user();
        $urlKembali = session(static::SESSION_URL_KEMBALI);
        $unitKerjaAsli = session(static::SESSION_UNIT_KERJA_ASLI);

        session()->forget([
            static::SESSION_PENGGUNA_ASLI,
            static::SESSION_URL_KEMBALI,
            static::SESSION_UNIT_KERJA_ASLI,
            UnitKerjaAktif::SESSION_KEY,
        ]);

        if (! $penggunaAsli?->is_active) {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            return route('login');
        }

        static::loginSebagai($penggunaAsli);

        if ($unitKerjaAsli !== null) {
            session()->put(UnitKerjaAktif::SESSION_KEY, $unitKerjaAsli);
        }

        Log::info('Impersonasi diakhiri.', [
            'pengguna_asli' => $penggunaAsli->username,
            'target' => $target?->username,
        ]);

        return static::urlAman($urlKembali) ?? Filament::getDefaultPanel()->getUrl();
    }

    public static function aktif(): bool
    {
        return session()->has(static::SESSION_PENGGUNA_ASLI);
    }

    public static function penggunaAsli(): ?User
    {
        $id = session(static::SESSION_PENGGUNA_ASLI);

        return $id === null ? null : User::query()->find($id);
    }

    private static function loginSebagai(User $user): void
    {
        $guard = Auth::guard();

        $guard->login($user);

        session()->put(
            'password_hash_'.Auth::getDefaultDriver(),
            $guard->hashPasswordForCookie($user->getAuthPassword()),
        );
    }

    /**
     * Hanya URL di dalam aplikasi ini yang dipakai sebagai tujuan kembali.
     */
    private static function urlAman(mixed $url): ?string
    {
        if (! is_string($url) || ! Str::startsWith($url, url('/'))) {
            return null;
        }

        return $url;
    }
}
