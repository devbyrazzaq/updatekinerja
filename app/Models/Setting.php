<?php

namespace App\Models;

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
     * Nilai bawaan yang dipakai selama pengaturan belum pernah disimpan.
     *
     * @var array<string, int|string>
     */
    public const DEFAULTS = [
        self::TAHUN_PER_PERIODE => 5,
        self::MAKS_REALISASI_BERJALAN => 2,
        self::MAKS_PROPOSAL_REALISASI => 3,
        self::MAKS_LAPORAN_REALISASI => 3,
        self::MAKS_UKURAN_PROPOSAL_MB => 10,
        self::MAKS_UKURAN_LAPORAN_MB => 10,
        self::MAKS_BUKTI_PEMASUKAN => 3,
        self::MAKS_UKURAN_BUKTI_PEMASUKAN_MB => 10,
        self::BRAND_NAMA => 'SIM KINERJA',
        self::BRAND_INSTANSI => 'Universitas Muhammadiyah Lamongan',
        self::BRAND_LOGO => '',
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
}
