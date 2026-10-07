<?php

namespace App\Models;

use App\Enums\EnumJenisLatarMasuk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Pengaturan perilaku sistem. Disimpan sebagai pasangan key-value dan dibaca
 * melalui helper bertipe, mis. Setting::tahunPerPeriode().
 */
class Setting extends Model
{
    /**
     * Jumlah tahun dalam satu periode jabatan, dihitung inklusif: tahun selesai
     * kelompok acuan = tahun mulai + nilai ini - 1.
     */
    public const TAHUN_PER_PERIODE = 'tahun_per_periode';

    /**
     * Jumlah realisasi yang boleh berjalan bersamaan pada satu unit kerja.
     */
    public const MAKS_REALISASI_BERJALAN = 'maks_realisasi_berjalan';

    /**
     * Unit kerja yang masih menyisakan realisasi belum tuntas pada tahun kerja
     * sebelumnya tidak boleh mengajukan realisasi baru (1) atau tetap boleh dengan
     * peringatan (0).
     */
    public const BLOKIR_TUNGGAKAN_TAHUN_LALU = 'blokir_tunggakan_tahun_lalu';

    /**
     * Jumlah maksimum berkas proposal yang boleh diunggah pada satu realisasi.
     */
    public const MAKS_PROPOSAL_REALISASI = 'maks_proposal_realisasi';

    /**
     * Jumlah maksimum berkas laporan yang boleh diunggah pada satu realisasi.
     */
    public const MAKS_LAPORAN_REALISASI = 'maks_laporan_realisasi';

    /**
     * Ukuran maksimum (MB) tiap berkas proposal realisasi.
     */
    public const MAKS_UKURAN_PROPOSAL_MB = 'maks_ukuran_proposal_mb';

    /**
     * Ukuran maksimum (MB) tiap berkas laporan realisasi.
     */
    public const MAKS_UKURAN_LAPORAN_MB = 'maks_ukuran_laporan_mb';

    /**
     * Jumlah maksimum berkas bukti tanda terima pada satu pemasukan.
     */
    public const MAKS_BUKTI_PEMASUKAN = 'maks_bukti_pemasukan';

    /**
     * Ukuran maksimum (MB) tiap berkas bukti tanda terima.
     */
    public const MAKS_UKURAN_BUKTI_PEMASUKAN_MB = 'maks_ukuran_bukti_pemasukan_mb';

    /**
     * Nama aplikasi yang tampil sebagai baris pertama brand panel.
     */
    public const BRAND_NAMA = 'brand_nama';

    /**
     * Nama instansi, dipakai sebagai baris kedua brand ketika pengguna melihat
     * seluruh unit kerja sekaligus.
     */
    public const BRAND_INSTANSI = 'brand_instansi';

    /**
     * Path logo brand pada disk publik; kosong berarti brand tampil tanpa logo.
     */
    public const BRAND_LOGO = 'brand_logo';

    /**
     * Disk penyimpanan logo brand. Berbeda dari berkas lain yang privat, logo perlu
     * URL tetap karena ikut tampil pada halaman yang belum terautentikasi.
     */
    public const BRAND_LOGO_DISK = 'public';

    /**
     * Jenis latar panel brand halaman masuk; lihat EnumJenisLatarMasuk.
     */
    public const MASUK_LATAR_JENIS = 'masuk_latar_jenis';

    /**
     * Path gambar latar halaman masuk pada disk publik; kosong berarti memakai gambar
     * bawaan sistem.
     */
    public const MASUK_LATAR_GAMBAR = 'masuk_latar_gambar';

    /**
     * Path berkas video latar halaman masuk pada disk publik.
     */
    public const MASUK_LATAR_VIDEO = 'masuk_latar_video';

    /**
     * Tautan video YouTube yang diputar sebagai latar halaman masuk.
     */
    public const MASUK_LATAR_YOUTUBE = 'masuk_latar_youtube';

    /**
     * Judul besar pada panel brand halaman masuk.
     */
    public const MASUK_JUDUL = 'masuk_judul';

    /**
     * Kalimat penjelas di bawah judul halaman masuk.
     */
    public const MASUK_DESKRIPSI = 'masuk_deskripsi';

    /**
     * Judul blok catatan akses pada kartu formulir masuk.
     */
    public const MASUK_CATATAN_JUDUL = 'masuk_catatan_judul';

    /**
     * Butir catatan akses, disimpan sebagai daftar dipisah baris baru karena nilai
     * pengaturan selalu berupa string tunggal.
     */
    public const MASUK_CATATAN = 'masuk_catatan';

    /**
     * Batas jumlah butir catatan akses. Kartu formulir masuk sengaja pendek: lebih dari
     * ini butir catatannya mendorong tombol Masuk keluar layar di ponsel.
     */
    public const MASUK_CATATAN_MAKS_BUTIR = 4;

    /**
     * Batas panjang satu butir catatan akses.
     */
    public const MASUK_CATATAN_MAKS_PANJANG = 120;

    /**
     * Disk penyimpanan berkas latar halaman masuk. Sama seperti logo brand: perlu URL
     * tetap karena tampil sebelum pengguna terautentikasi.
     */
    public const MASUK_LATAR_DISK = 'public';

    /**
     * Gambar bawaan halaman masuk. Dilayani langsung dari `public/` dan tidak pernah
     * dihapus, sehingga aksi Kembalikan ke Bawaan selalu punya tujuan.
     */
    public const MASUK_LATAR_BAWAAN = 'images/campus.webp';

    /**
     * Jabatan penanda tangan yang tercetak di atas nama pada blok tanda tangan
     * laporan pencairan.
     */
    public const PENANDATANGAN_JABATAN = 'penandatangan_jabatan';

    /**
     * Nama pimpinan yang menandatangani laporan, ditulis lengkap beserta gelarnya.
     */
    public const PENANDATANGAN_NAMA = 'penandatangan_nama';

    /**
     * Nomor karyawan penanda tangan, tercetak di bawah namanya.
     */
    public const PENANDATANGAN_NOMOR = 'penandatangan_nomor';

    /**
     * Kota yang mendahului tanggal pada blok tanda tangan, mis. "Lamongan, 17
     * Agustus 2026".
     */
    public const PENANDATANGAN_KOTA = 'penandatangan_kota';

    /**
     * Nilai bawaan yang dipakai selama pengaturan belum pernah disimpan.
     *
     * @var array<string, int|string>
     */
    public const DEFAULTS = [
        self::TAHUN_PER_PERIODE => 5,
        self::MAKS_REALISASI_BERJALAN => 2,
        self::BLOKIR_TUNGGAKAN_TAHUN_LALU => 1,
        self::MAKS_PROPOSAL_REALISASI => 3,
        self::MAKS_LAPORAN_REALISASI => 3,
        self::MAKS_UKURAN_PROPOSAL_MB => 10,
        self::MAKS_UKURAN_LAPORAN_MB => 10,
        self::MAKS_BUKTI_PEMASUKAN => 3,
        self::MAKS_UKURAN_BUKTI_PEMASUKAN_MB => 10,
        self::BRAND_NAMA => 'SIM KINERJA',
        self::BRAND_INSTANSI => 'Universitas Muhammadiyah Lamongan',
        self::BRAND_LOGO => '',
        self::MASUK_LATAR_JENIS => EnumJenisLatarMasuk::Gambar->value,
        self::MASUK_LATAR_GAMBAR => '',
        self::MASUK_LATAR_VIDEO => '',
        self::MASUK_LATAR_YOUTUBE => '',
        self::MASUK_JUDUL => 'Kelola kinerja unit kerja dalam satu ruang.',
        self::MASUK_DESKRIPSI => 'Satu akun untuk merencanakan program kerja, mengajukan anggaran, mencatat pemasukan, dan memantau realisasi hingga selesai.',
        self::MASUK_CATATAN_JUDUL => 'Catatan akses',
        self::MASUK_CATATAN => "Gunakan username dan password yang diberikan pengelola sistem.\nHubungi admin jika akun belum aktif atau lupa akses.",
        self::PENANDATANGAN_JABATAN => '',
        self::PENANDATANGAN_NAMA => '',
        self::PENANDATANGAN_NOMOR => '',
        self::PENANDATANGAN_KOTA => '',
    ];

    protected const CACHE_KEY = 'settings.all';

    protected $fillable = [
        'key',
        'value',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => static::forgetCache());
        static::deleted(fn () => static::forgetCache());
    }

    /**
     * Seluruh pengaturan (key => value) digabung dengan nilai bawaan.
     *
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        return Cache::rememberForever(
            static::CACHE_KEY,
            fn (): array => [
                ...static::DEFAULTS,
                ...static::query()->pluck('value', 'key')->all(),
            ],
        );
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::values()[$key] ?? $default ?? static::DEFAULTS[$key] ?? null;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }

    public static function forgetCache(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    public static function tahunPerPeriode(): int
    {
        return max(1, (int) static::get(self::TAHUN_PER_PERIODE));
    }

    public static function maksRealisasiBerjalan(): int
    {
        return max(1, (int) static::get(self::MAKS_REALISASI_BERJALAN));
    }

    /**
     * Tunggakan realisasi tahun kerja sebelumnya menahan pengajuan realisasi baru
     * unit kerja yang bersangkutan.
     */
    public static function blokirTunggakanTahunLalu(): bool
    {
        return (bool) (int) static::get(self::BLOKIR_TUNGGAKAN_TAHUN_LALU);
    }

    public static function maksProposalRealisasi(): int
    {
        return max(1, (int) static::get(self::MAKS_PROPOSAL_REALISASI));
    }

    public static function maksLaporanRealisasi(): int
    {
        return max(1, (int) static::get(self::MAKS_LAPORAN_REALISASI));
    }

    /**
     * Ukuran maksimum berkas proposal dalam kilobyte (dipakai FileUpload::maxSize).
     */
    public static function maksUkuranProposalKb(): int
    {
        return max(1, (int) static::get(self::MAKS_UKURAN_PROPOSAL_MB)) * 1024;
    }

    /**
     * Ukuran maksimum berkas laporan dalam kilobyte (dipakai FileUpload::maxSize).
     */
    public static function maksUkuranLaporanKb(): int
    {
        return max(1, (int) static::get(self::MAKS_UKURAN_LAPORAN_MB)) * 1024;
    }

    public static function maksBuktiPemasukan(): int
    {
        return max(1, (int) static::get(self::MAKS_BUKTI_PEMASUKAN));
    }

    /**
     * Ukuran maksimum berkas bukti dalam kilobyte (dipakai FileUpload::maxSize).
     */
    public static function maksUkuranBuktiPemasukanKb(): int
    {
        return max(1, (int) static::get(self::MAKS_UKURAN_BUKTI_PEMASUKAN_MB)) * 1024;
    }

    /**
     * Nama aplikasi pada brand panel; kembali ke bawaan bila dikosongkan.
     */
    public static function brandNama(): string
    {
        $nama = trim((string) static::get(self::BRAND_NAMA));

        return $nama !== '' ? $nama : (string) self::DEFAULTS[self::BRAND_NAMA];
    }

    /**
     * Nama instansi pada brand panel; kembali ke bawaan bila dikosongkan.
     */
    public static function brandInstansi(): string
    {
        $instansi = trim((string) static::get(self::BRAND_INSTANSI));

        return $instansi !== '' ? $instansi : (string) self::DEFAULTS[self::BRAND_INSTANSI];
    }

    /**
     * Jabatan penanda tangan laporan; kosong berarti barisnya tidak dicetak.
     */
    public static function penandatanganJabatan(): string
    {
        return trim((string) static::get(self::PENANDATANGAN_JABATAN));
    }

    /**
     * Nama pimpinan penanda tangan laporan lengkap dengan gelarnya; kosong berarti
     * laporan memakai nama pengguna yang mengunduhnya.
     */
    public static function penandatanganNama(): string
    {
        return trim((string) static::get(self::PENANDATANGAN_NAMA));
    }

    /**
     * Nomor karyawan penanda tangan laporan; kosong berarti barisnya tidak dicetak.
     */
    public static function penandatanganNomor(): string
    {
        return trim((string) static::get(self::PENANDATANGAN_NOMOR));
    }

    /**
     * Kota pada baris tanggal blok tanda tangan; kosong berarti hanya tanggalnya
     * yang dicetak.
     */
    public static function penandatanganKota(): string
    {
        return trim((string) static::get(self::PENANDATANGAN_KOTA));
    }

    /**
     * Path logo brand pada disk publik, `null` bila belum diunggah.
     */
    public static function brandLogoPath(): ?string
    {
        $path = trim((string) static::get(self::BRAND_LOGO));

        return $path !== '' ? $path : null;
    }

    /**
     * URL logo brand, `null` bila belum diunggah atau berkasnya sudah tidak ada.
     */
    public static function brandLogoUrl(): ?string
    {
        $path = static::brandLogoPath();

        if ($path === null) {
            return null;
        }

        $disk = Storage::disk(self::BRAND_LOGO_DISK);

        return $disk->exists($path) ? $disk->url($path) : null;
    }

    /**
     * Judul besar halaman masuk; kembali ke bawaan bila dikosongkan.
     */
    public static function masukJudul(): string
    {
        $judul = trim((string) static::get(self::MASUK_JUDUL));

        return $judul !== '' ? $judul : (string) self::DEFAULTS[self::MASUK_JUDUL];
    }

    /**
     * Kalimat penjelas halaman masuk; kembali ke bawaan bila dikosongkan.
     */
    public static function masukDeskripsi(): string
    {
        $deskripsi = trim((string) static::get(self::MASUK_DESKRIPSI));

        return $deskripsi !== '' ? $deskripsi : (string) self::DEFAULTS[self::MASUK_DESKRIPSI];
    }

    /**
     * Judul blok catatan akses; kembali ke bawaan bila dikosongkan.
     */
    public static function masukCatatanJudul(): string
    {
        $judul = trim((string) static::get(self::MASUK_CATATAN_JUDUL));

        return $judul !== '' ? $judul : (string) self::DEFAULTS[self::MASUK_CATATAN_JUDUL];
    }

    /**
     * Butir catatan akses halaman masuk. Daftar kosong berarti bloknya tidak ditampilkan
     * sama sekali, bukan tampil tanpa isi.
     *
     * @return list<string>
     */
    public static function masukCatatan(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) static::get(self::MASUK_CATATAN)) ?: [])
            ->map(fn (string $butir): string => trim($butir))
            ->filter(fn (string $butir): bool => $butir !== '')
            ->map(fn (string $butir): string => mb_substr($butir, 0, self::MASUK_CATATAN_MAKS_PANJANG))
            ->take(self::MASUK_CATATAN_MAKS_BUTIR)
            ->values()
            ->all();
    }

    /**
     * Butir catatan dalam bentuk baris repeater.
     *
     * @return list<array{butir: string}>
     */
    public static function barisCatatanMasuk(): array
    {
        return array_map(
            static fn (string $butir): array => ['butir' => $butir],
            static::masukCatatan(),
        );
    }

    /**
     * Kebalikan barisCatatanMasuk(): merangkai baris repeater menjadi nilai pengaturan.
     *
     * @param  array<int, array{butir?: string|null}>  $baris
     */
    public static function rangkaiCatatanMasuk(array $baris): string
    {
        return collect($baris)
            ->map(fn (mixed $satu): string => trim((string) (is_array($satu) ? ($satu['butir'] ?? '') : $satu)))
            ->filter(fn (string $satu): bool => $satu !== '')
            ->take(self::MASUK_CATATAN_MAKS_BUTIR)
            ->implode("\n");
    }

    /**
     * Jenis latar yang dipilih di pengaturan, belum tentu sama dengan yang benar-benar
     * bisa ditampilkan — lihat masukLatarJenisTerpasang().
     */
    public static function masukLatarJenis(): EnumJenisLatarMasuk
    {
        return EnumJenisLatarMasuk::tryFrom((string) static::get(self::MASUK_LATAR_JENIS))
            ?? EnumJenisLatarMasuk::Gambar;
    }

    /**
     * Jenis latar yang benar-benar dipasang halaman masuk. Video atau YouTube yang
     * berkasnya hilang atau tautannya tidak sah turun ke gambar, supaya halaman masuk
     * tidak pernah tampil berlatar kosong.
     */
    public static function masukLatarJenisTerpasang(): EnumJenisLatarMasuk
    {
        return match (static::masukLatarJenis()) {
            EnumJenisLatarMasuk::Video => static::masukLatarVideoUrl() !== null
                ? EnumJenisLatarMasuk::Video
                : EnumJenisLatarMasuk::Gambar,
            EnumJenisLatarMasuk::Youtube => static::masukLatarYoutubeId() !== null
                ? EnumJenisLatarMasuk::Youtube
                : EnumJenisLatarMasuk::Gambar,
            EnumJenisLatarMasuk::Gambar => EnumJenisLatarMasuk::Gambar,
        };
    }

    /**
     * Path gambar latar halaman masuk pada disk publik, `null` bila belum diunggah.
     */
    public static function masukLatarGambarPath(): ?string
    {
        $path = trim((string) static::get(self::MASUK_LATAR_GAMBAR));

        return $path !== '' ? $path : null;
    }

    /**
     * URL gambar latar halaman masuk; selalu terisi — jatuh ke gambar bawaan bila belum
     * ada unggahan atau berkasnya sudah tidak ada.
     */
    public static function masukLatarGambarUrl(): string
    {
        return static::urlBerkasMasuk(static::masukLatarGambarPath())
            ?? asset(self::MASUK_LATAR_BAWAAN);
    }

    /**
     * Path berkas video latar pada disk publik, `null` bila belum diunggah.
     */
    public static function masukLatarVideoPath(): ?string
    {
        $path = trim((string) static::get(self::MASUK_LATAR_VIDEO));

        return $path !== '' ? $path : null;
    }

    /**
     * URL berkas video latar, `null` bila belum diunggah atau berkasnya sudah tidak ada.
     */
    public static function masukLatarVideoUrl(): ?string
    {
        return static::urlBerkasMasuk(static::masukLatarVideoPath());
    }

    /**
     * Tautan YouTube yang tersimpan apa adanya, `null` bila kosong.
     */
    public static function masukLatarYoutube(): ?string
    {
        $tautan = trim((string) static::get(self::MASUK_LATAR_YOUTUBE));

        return $tautan !== '' ? $tautan : null;
    }

    /**
     * Id video YouTube hasil penguraian tautan tersimpan, `null` bila tautannya tidak
     * dikenali.
     */
    public static function masukLatarYoutubeId(): ?string
    {
        return static::uraiYoutubeId(static::masukLatarYoutube());
    }

    /**
     * Id video dari berbagai bentuk tautan YouTube (watch, youtu.be, embed, shorts,
     * live) maupun id yang diketik langsung. `null` berarti tautannya tidak dikenali —
     * dipakai juga sebagai aturan validasi isian.
     */
    public static function uraiYoutubeId(?string $tautan): ?string
    {
        $tautan = trim((string) $tautan);

        if ($tautan === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $tautan) === 1) {
            return $tautan;
        }

        $pola = [
            '#youtu\.be/([A-Za-z0-9_-]{11})#',
            '#youtube\.com/watch\?(?:[^\s]*&)?v=([A-Za-z0-9_-]{11})#',
            '#youtube\.com/(?:embed|v|shorts|live)/([A-Za-z0-9_-]{11})#',
        ];

        foreach ($pola as $satu) {
            if (preg_match($satu, $tautan, $cocok) === 1) {
                return $cocok[1];
            }
        }

        return null;
    }

    /**
     * Mengembalikan seluruh setelan halaman masuk ke bawaan sekaligus membuang berkas
     * yang pernah diunggah, karena setelah ini tidak ada lagi yang merujuknya.
     */
    public static function pulihkanBawaanHalamanMasuk(): void
    {
        $disk = Storage::disk(self::MASUK_LATAR_DISK);

        foreach ([static::masukLatarGambarPath(), static::masukLatarVideoPath()] as $path) {
            if ($path !== null && $disk->exists($path)) {
                $disk->delete($path);
            }
        }

        $bawaan = [
            self::MASUK_LATAR_JENIS,
            self::MASUK_LATAR_GAMBAR,
            self::MASUK_LATAR_VIDEO,
            self::MASUK_LATAR_YOUTUBE,
            self::MASUK_JUDUL,
            self::MASUK_DESKRIPSI,
            self::MASUK_CATATAN_JUDUL,
            self::MASUK_CATATAN,
        ];

        foreach ($bawaan as $kunci) {
            static::set($kunci, self::DEFAULTS[$kunci]);
        }
    }

    /**
     * URL berkas halaman masuk pada disk publik, `null` bila path kosong atau berkasnya
     * sudah tidak ada.
     */
    protected static function urlBerkasMasuk(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $disk = Storage::disk(self::MASUK_LATAR_DISK);

        return $disk->exists($path) ? $disk->url($path) : null;
    }
}
