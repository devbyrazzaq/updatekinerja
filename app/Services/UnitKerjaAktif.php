<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Builder;

/**
 * Unit kerja yang sedang aktif bagi pengguna yang berwenang atas lebih dari satu unit.
 *
 * Seluruh menu memandang tepat satu unit pada satu waktu; pilihan disimpan di session
 * dan dapat diganti lewat pengalih unit di topbar. Nilainya selalu diverifikasi ulang
 * terhadap unit yang benar-benar boleh diakses, sehingga pilihan basi (unit dicabut,
 * dinonaktifkan, atau dimanipulasi) otomatis jatuh kembali ke unit bawaan pengguna.
 */
class UnitKerjaAktif
{
    public const SESSION_KEY = 'unit_kerja_aktif';

    /**
     * Id unit kerja yang sedang aktif; `null` bila pengguna tidak punya unit sama
     * sekali atau bukan pengguna yang sedang login (mis. dipanggil dari console).
     */
    public static function id(?User $user = null): ?int
    {
        $user ??= static::pengguna();

        if (! $user instanceof User || ! static::miliknyaSendiri($user)) {
            return null;
        }

        $tersedia = PermissionRegistrar::allPermittedUnitIds($user);

        if ($tersedia->isEmpty()) {
            return null;
        }

        $pilihan = static::pilihanTersimpan();

        if ($pilihan !== null && $tersedia->contains($pilihan)) {
            return $pilihan;
        }

        // Tanpa pilihan yang sah, unit bawaan pengguna dipakai lebih dulu.
        return $tersedia->contains($user->unit_kerja_id)
            ? (int) $user->unit_kerja_id
            : (int) $tersedia->first();
    }

    /**
     * Simpan unit aktif. Id yang bukan haknya diabaikan diam-diam.
     */
    public static function set(?int $unitKerjaId): void
    {
        $user = static::pengguna();
        $session = static::session();

        if ($session === null) {
            return;
        }

        if ($unitKerjaId === null) {
            $session->forget(static::SESSION_KEY);

            return;
        }

        if ($user instanceof User && PermissionRegistrar::allPermittedUnitIds($user)->contains($unitKerjaId)) {
            $session->put(static::SESSION_KEY, $unitKerjaId);
        }
    }

    /**
     * Unit kerja yang boleh dipilih pengguna, id => nama, urut abjad.
     *
     * @return array<int, string>
     */
    public static function opsi(?User $user = null): array
    {
        $user ??= static::pengguna();

        if (! $user instanceof User) {
            return [];
        }

        return UnitKerja::query()
            ->whereIn('id', PermissionRegistrar::allPermittedUnitIds($user)->all())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Batasi kueri unit kerja pada cakupan pengguna saat ini, dipakai pilihan
     * `unit_kerja_id` pada form supaya data hanya bisa dibuat untuk unit yang sedang
     * aktif. Pengguna berakses penuh tidak dibatasi.
     *
     * @param  Builder<UnitKerja>  $query
     * @return Builder<UnitKerja>
     */
    public static function batasiKueri(Builder $query): Builder
    {
        $user = static::pengguna();

        if ($user === null || $user->isPrivileged()) {
            return $query;
        }

        return $query->whereIn('id', PermissionRegistrar::permittedUnitIds($user)->all());
    }

    /**
     * Nama cakupan data yang sedang dilihat: nama unit kerja aktif, atau nama
     * instansi bila pengguna berwenang atas seluruh unit sekaligus. Dipakai brand
     * panel sebagai keterangan di bawah nama aplikasi.
     */
    public static function namaCakupan(?User $user = null): string
    {
        $user ??= static::pengguna();

        if ($user === null || $user->isPrivileged()) {
            return Setting::brandInstansi();
        }

        $id = static::id($user);

        if ($id === null) {
            return Setting::brandInstansi();
        }

        return UnitKerja::query()->whereKey($id)->value('name') ?? Setting::brandInstansi();
    }

    /**
     * Pengalih unit hanya relevan bagi pengguna berunit ganda yang datanya memang
     * dibatasi; pengguna dengan akses penuh sudah melihat seluruh unit sekaligus.
     */
    public static function dapatBerganti(?User $user = null): bool
    {
        $user ??= static::pengguna();

        if (! $user instanceof User || $user->isPrivileged()) {
            return false;
        }

        return PermissionRegistrar::allPermittedUnitIds($user)->count() > 1;
    }

    protected static function pengguna(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Session hanya dibaca untuk pengguna yang sedang login — memanggil fungsi ini
     * bagi pengguna lain (mis. saat menghitung cakupan data orang lain) akan salah
     * memakai pilihan milik pengguna yang sedang login.
     */
    protected static function miliknyaSendiri(User $user): bool
    {
        return static::pengguna()?->getKey() === $user->getKey();
    }

    protected static function pilihanTersimpan(): ?int
    {
        $tersimpan = static::session()?->get(static::SESSION_KEY);

        return is_numeric($tersimpan) ? (int) $tersimpan : null;
    }

    /**
     * Session store yang benar-benar aktif; `null` di luar konteks request (console,
     * queue, seeder) sehingga pemanggilnya kembali ke perilaku tanpa unit aktif.
     */
    protected static function session(): ?Session
    {
        if (! app()->bound('session.store')) {
            return null;
        }

        $session = app('session.store');

        return $session instanceof Session && $session->isStarted() ? $session : null;
    }
}
