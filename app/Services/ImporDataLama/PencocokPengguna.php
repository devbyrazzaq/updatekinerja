<?php

namespace App\Services\ImporDataLama;

use App\Enums\EnumRole;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Menjodohkan akun pengguna aplikasi lama dengan akun yang sudah ada di sistem ini,
 * lalu membuatkan akun baru untuk yang belum punya padanan.
 *
 * Pencocokan bertingkat: username/NIDN lebih dulu (paling pasti), lalu alamat surel,
 * terakhir nama yang dinormalkan. Akun yang terpaksa dibuat memakai hash kata sandi
 * aslinya lewat {@see User::setHashedPassword()} sehingga pemiliknya tetap bisa masuk
 * dengan kata sandi lama.
 */
class PencocokPengguna
{
    /**
     * Indeks pencarian akun yang sudah ada, dibangun sekali agar tidak menembak
     * basis data untuk tiap baris data lama.
     *
     * Pembangunannya sengaja ditunda sampai pemakaian pertama: satu proses impor bisa
     * menggarap beberapa sumber berurutan, dan sumber berikutnya harus melihat akun
     * yang baru dibuat sumber sebelumnya agar tidak menggandakannya.
     *
     * @var array{username: array<string, int>, email: array<string, int>, nama: array<string, int>}|null
     */
    private ?array $indeks = null;

    /**
     * Jumlah akun yang dibuat selama proses impor.
     */
    private int $dibuat = 0;

    public function jumlahDibuat(): int
    {
        return $this->dibuat;
    }

    /**
     * Akun yang cocok dengan identitas lama, atau null bila tidak ditemukan.
     */
    public function cari(?string $username, ?string $email, ?string $nama): ?int
    {
        $this->indeks ??= $this->bangunIndeks();

        return $this->indeks['username'][$this->kunci($username)]
            ?? $this->indeks['email'][$this->kunci($email)]
            ?? $this->indeks['nama'][$this->kunci($nama)]
            ?? null;
    }

    /**
     * Akun yang cocok, atau akun baru bila belum ada padanannya.
     *
     * @param  array{username: ?string, email: ?string, nama: string, password: ?string, aktif: bool, unit_kerja_id: ?int, roles: array<int, string>}  $atribut
     */
    public function cariAtauBuat(array $atribut): int
    {
        $ditemukan = $this->cari($atribut['username'], $atribut['email'], $atribut['nama']);

        if ($ditemukan !== null) {
            return $ditemukan;
        }

        return $this->buat($atribut);
    }

    /**
     * Membuat akun baru dari data lama. Username dan surel dijaga tetap unik karena
     * keduanya berkolom unik pada tabel `users`.
     *
     * @param  array{username: ?string, email: ?string, nama: string, password: ?string, aktif: bool, unit_kerja_id: ?int, roles: array<int, string>}  $atribut
     */
    private function buat(array $atribut): int
    {
        $username = $this->usernameUnik($atribut['username'], $atribut['nama']);

        $user = new User;
        $user->fill([
            'name' => $atribut['nama'],
            'username' => $username,
            'email' => $this->emailUnik($atribut['email'], $username),
            'unit_kerja_id' => $atribut['unit_kerja_id'],
            'is_active' => $atribut['aktif'],
        ]);

        // Hash lama dipasang apa adanya; bila tidak ada, akun dikunci dengan hash acak
        // yang tidak mungkin ditebak sampai kata sandinya diatur ulang.
        $user->setHashedPassword($atribut['password'] ?: bcrypt(Str::random(40)));
        $user->save();

        $roles = $atribut['roles'];

        if ($atribut['unit_kerja_id'] !== null) {
            $unitKerja = UnitKerja::find($atribut['unit_kerja_id']);

            if ($unitKerja !== null) {
                $roles[] = EnumRole::unitScopeName($unitKerja->name);
            }
        }

        if ($roles !== []) {
            $user->syncRoles($roles);
        }

        $this->daftarkan($user);
        $this->dibuat++;

        return $user->id;
    }

    /**
     * Username yang belum dipakai akun lain. Bila username lama sudah dipakai (atau
     * kosong), dipakai turunan dari namanya dengan akhiran angka.
     */
    private function usernameUnik(?string $username, string $nama): string
    {
        $kandidat = filled($username) ? Str::slug($username) : Str::slug($nama);
        $kandidat = $kandidat !== '' ? $kandidat : 'pengguna';
        $dasar = $kandidat;
        $urutan = 1;

        while (User::where('username', $kandidat)->exists()) {
            $kandidat = $dasar.'-'.(++$urutan);
        }

        return $kandidat;
    }

    private function emailUnik(?string $email, string $username): string
    {
        $kandidat = filled($email) ? $email : $username.'@umla.ac.id';
        $dasar = Str::before($kandidat, '@');
        $domain = Str::after($kandidat, '@');
        $urutan = 1;

        while (User::where('email', $kandidat)->exists()) {
            $kandidat = $dasar.'+'.(++$urutan).'@'.$domain;
        }

        return $kandidat;
    }

    /**
     * @return array{username: array<string, int>, email: array<string, int>, nama: array<string, int>}
     */
    private function bangunIndeks(): array
    {
        $indeks = ['username' => [], 'email' => [], 'nama' => []];

        User::query()
            ->select(['id', 'username', 'email', 'name'])
            ->get()
            ->each(function (User $user) use (&$indeks): void {
                $indeks['username'][$this->kunci($user->username)] ??= $user->id;
                $indeks['email'][$this->kunci($user->email)] ??= $user->id;
                $indeks['nama'][$this->kunci($user->name)] ??= $user->id;
            });

        unset($indeks['username'][''], $indeks['email'][''], $indeks['nama']['']);

        return $indeks;
    }

    private function daftarkan(User $user): void
    {
        $this->indeks ??= $this->bangunIndeks();
        $this->indeks['username'][$this->kunci($user->username)] ??= $user->id;
        $this->indeks['email'][$this->kunci($user->email)] ??= $user->id;
        $this->indeks['nama'][$this->kunci($user->name)] ??= $user->id;
    }

    /**
     * Kunci pencarian yang mengabaikan beda huruf besar/kecil dan spasi ganda.
     */
    private function kunci(?string $nilai): string
    {
        return Str::lower(trim((string) preg_replace('/\s+/u', ' ', (string) $nilai)));
    }

    /**
     * Peta id pengguna lama ke id pengguna baru untuk sekumpulan baris tabel users
     * aplikasi lama.
     *
     * @param  Collection<int, array{id: int, username: ?string, email: ?string, nama: string, password: ?string, aktif: bool, unit_kerja_id: ?int, roles: array<int, string>}>  $penggunaLama
     * @return array<int, int>
     */
    public function petakan(Collection $penggunaLama): array
    {
        return $penggunaLama
            ->mapWithKeys(fn (array $pengguna): array => [$pengguna['id'] => $this->cariAtauBuat($pengguna)])
            ->all();
    }
}
