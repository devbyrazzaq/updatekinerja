<?php

namespace App\Services;

use App\Enums\EnumStatusTahunKerja;
use App\Models\KelompokAcuan;
use App\Models\TahunKerja;
use Illuminate\Database\Eloquent\Builder;

/**
 * Konteks kerja aktif berupa dua slot yang hidup berdampingan: satu tahun kerja
 * Berjalan dan satu tahun kerja Perencanaan, masing-masing dipasangkan dengan
 * kelompok acuannya sendiri di halaman Pengaturan Program Kerja.
 *
 * Penyaringan dipecah menurut fase, dan pemisahan inilah yang menjaga agar
 * perencanaan tahun mendatang tidak pernah menyentuh pelaksanaan:
 *
 * - Fase perencanaan (pagu anggaran dan penawaran program kerja) mencakup tahun
 *   Berjalan dan tahun Perencanaan sekaligus, karena keduanya memang disusun
 *   berdampingan pada satu menu.
 * - Fase pelaksanaan (realisasi, pencairan, pelaporan, dan seluruh verifikasinya)
 *   hanya mencakup tahun Berjalan.
 * - Menu yang dipecah per slot — Daftar Program Kerja, Pengajuan Program Kerja, dan
 *   Verifikasi Pengajuan — memakai {@see applySlot()}: satu salinan di grup
 *   Pelaksanaan untuk tahun Berjalan, satu salinan lagi di grup Perencanaan untuk
 *   tahun Perencanaan.
 *
 * Tahun Penutupan sengaja tidak ikut fase pelaksanaan: begitu tahun kerja berganti,
 * seluruh menu harian berbicara tentang tahun yang baru saja dan angka anggarannya
 * tidak lagi tercampur data tahun lalu. Realisasi tahun lalu yang belum tuntas tidak
 * hilang — ia pindah ke halaman Penyelesaian Tahun Lalu, satu-satunya tempat
 * tunggakan itu dikerjakan sampai tahun tersebut boleh dikunci.
 *
 * Fase yang tidak punya penghuni ditutup rapat: menu-menunya tidak menampilkan apa
 * pun sampai tahun kerja baru dijalankan. Ini yang membuat tombol Akhiri Tahun Kerja
 * benar-benar mengunci, bukan sekadar mengosongkan slot. Kelonggaran hanya diberikan
 * pada instalasi yang belum pernah dikonfigurasi ({@see pernahDikonfigurasi()}),
 * supaya data lama tetap terjangkau dan konteks pertama masih bisa dibentuk.
 */
class KonteksProgramKerja
{
    /**
     * Tahun kerja yang sedang dijalankan.
     */
    public static function tahunBerjalan(): ?TahunKerja
    {
        return TahunKerja::berjalan();
    }

    /**
     * Tahun kerja mendatang yang sedang direncanakan.
     */
    public static function tahunPerencanaan(): ?TahunKerja
    {
        return TahunKerja::perencanaan();
    }

    /**
     * Tahun kerja yang datanya boleh disusun pada fase perencanaan.
     *
     * @return array<int, int>
     */
    public static function tahunPerencanaanIds(): array
    {
        return array_values(array_filter([
            static::tahunBerjalan()?->getKey(),
            static::tahunPerencanaan()?->getKey(),
        ]));
    }

    /**
     * Tahun kerja yang realisasinya boleh berjalan: hanya tahun Berjalan. Tahun
     * Perencanaan belum sampai ke pelaksanaan, dan tahun Penutupan sudah lewat —
     * tunggakannya dikerjakan lewat {@see tahunPenutupanIds()}.
     *
     * @return array<int, int>
     */
    public static function tahunPelaksanaanIds(): array
    {
        return array_values(array_filter([static::tahunBerjalan()?->getKey()]));
    }

    /**
     * Tahun kerja penghuni satu slot konteks. Hanya {@see EnumStatusTahunKerja::Berjalan}
     * dan {@see EnumStatusTahunKerja::Perencanaan} yang menempati slot; status lain
     * tidak pernah punya penghuni tunggal.
     */
    public static function tahunSlot(EnumStatusTahunKerja $slot): ?TahunKerja
    {
        return match ($slot) {
            EnumStatusTahunKerja::Berjalan => static::tahunBerjalan(),
            EnumStatusTahunKerja::Perencanaan => static::tahunPerencanaan(),
            default => null,
        };
    }

    /**
     * @return array<int, int>
     */
    public static function tahunSlotIds(EnumStatusTahunKerja $slot): array
    {
        return array_values(array_filter([static::tahunSlot($slot)?->getKey()]));
    }

    /**
     * Batasi record ke satu slot konteks saja. Dipakai menu yang sengaja dipisah per
     * slot: grup Pelaksanaan menggarap tahun Berjalan, grup Perencanaan menggarap
     * tahun Perencanaan, dan keduanya tidak pernah saling menampilkan data.
     *
     * Perlakuan slot kosong berbeda menurut slotnya:
     *
     * - Slot Berjalan mengikuti kelonggaran fase pelaksanaan — tanpa tahun berjalan
     *   penyaringan dilewati karena sistem dianggap belum dikonfigurasi.
     * - Slot Perencanaan tidak punya kelonggaran itu: selama belum ada tahun mendatang
     *   yang disiapkan, menu Perencanaan memang tidak boleh menampilkan apa pun.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applySlot(Builder $query, EnumStatusTahunKerja $slot, string $column = 'tahun_kerja_id'): Builder
    {
        $ids = static::tahunSlotIds($slot);

        return $slot === EnumStatusTahunKerja::Berjalan
            ? static::batasi($query, $column, $ids)
            : $query->whereIn($column, $ids);
    }

    /**
     * Versi {@see applySlot()} untuk record yang mewarisi tahun kerja lewat relasi,
     * mis. 'penawaranProgramKerja' pada Pengajuan Program Kerja.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applySlotVia(Builder $query, EnumStatusTahunKerja $slot, string $relationPath): Builder
    {
        $ids = static::tahunSlotIds($slot);

        if ($slot === EnumStatusTahunKerja::Berjalan) {
            return static::batasiVia($query, $relationPath, $ids);
        }

        return $query->whereHas(
            $relationPath,
            fn (Builder $query): Builder => $query->whereIn('tahun_kerja_id', $ids),
        );
    }

    /**
     * Tahun kerja yang sudah ditinggalkan namun belum dikunci. Data tahun-tahun ini
     * tidak lagi muncul di menu harian; hanya halaman Penyelesaian Tahun Lalu yang
     * membacanya.
     *
     * @return array<int, int>
     */
    public static function tahunPenutupanIds(): array
    {
        return TahunKerja::penutupan()->modelKeys();
    }

    /**
     * Kelompok acuan yang dipakai slot tahun berjalan. Tahun kerja lama yang belum
     * memasangkan kelompok acuannya jatuh kembali ke kelompok acuan aktif.
     */
    public static function kelompokAcuan(): ?KelompokAcuan
    {
        return static::tahunBerjalan()?->kelompokAcuan ?? KelompokAcuan::active();
    }

    /**
     * Konteks tahun berjalan sudah lengkap: tahun kerja beserta kelompok acuannya.
     */
    public static function siap(): bool
    {
        return static::tahunBerjalan() !== null && static::kelompokAcuan() !== null;
    }

    /**
     * Sistem sudah pernah menjalankan tahun kerja. Dipakai membedakan slot yang
     * sengaja dikosongkan lewat Akhiri Tahun Kerja — menunya harus tertutup — dari
     * instalasi baru yang memang belum dikonfigurasi.
     *
     * Status saja tidak cukup sebagai penanda: tahun kerja yang sudah dikunci
     * kembali berstatus Selesai, status yang sama dengan tahun yang belum pernah
     * dijalankan sama sekali. Karena itu stempel penutupan dan penguncian ikut
     * diperiksa, dan sekali terisi penanda ini tidak pernah mati lagi.
     */
    public static function pernahDikonfigurasi(): bool
    {
        return TahunKerja::query()
            ->whereIn('status', [
                EnumStatusTahunKerja::Berjalan,
                EnumStatusTahunKerja::Penutupan,
            ])
            ->orWhereNotNull('ditutup_pada')
            ->orWhereNotNull('dikunci_pada')
            ->exists();
    }

    /**
     * Kelompok acuan yang dipakai kedua slot konteks, dipakai menyaring Acuan
     * Program Kerja.
     *
     * @return array<int, int>
     */
    public static function kelompokAcuanIds(): array
    {
        $ids = array_filter([
            static::tahunBerjalan()?->kelompok_acuan_id,
            static::tahunPerencanaan()?->kelompok_acuan_id,
        ]);

        return $ids === []
            ? array_values(array_filter([KelompokAcuan::active()?->getKey()]))
            : array_values(array_unique($ids));
    }

    /**
     * Batasi record yang menyimpan kelompok acuan langsung, mis. Acuan Program Kerja.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applyKelompokAcuan(Builder $query, string $column = 'kelompok_acuan_id'): Builder
    {
        $ids = static::kelompokAcuanIds();

        if ($ids === []) {
            return $query;
        }

        return $query->whereIn($column, $ids);
    }

    /**
     * Batasi record fase perencanaan yang menyimpan tahun kerja langsung, mis. Pagu
     * Anggaran dan Penawaran Program Kerja.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applyPerencanaan(Builder $query, string $column = 'tahun_kerja_id'): Builder
    {
        return static::batasi($query, $column, static::tahunPerencanaanIds());
    }

    /**
     * Batasi record fase perencanaan yang mewarisi tahun kerja lewat sebuah jalur
     * relasi, mis. 'penawaranProgramKerja' pada Pengajuan Program Kerja.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applyPerencanaanVia(Builder $query, string $relationPath): Builder
    {
        return static::batasiVia($query, $relationPath, static::tahunPerencanaanIds());
    }

    /**
     * Batasi record fase pelaksanaan yang menyimpan tahun kerja langsung, mis.
     * Jadwal Pencairan.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applyPelaksanaan(Builder $query, string $column = 'tahun_kerja_id'): Builder
    {
        return static::batasi($query, $column, static::tahunPelaksanaanIds());
    }

    /**
     * Batasi record fase pelaksanaan yang mewarisi tahun kerja dari penawaran induk
     * lewat sebuah jalur relasi, mis. 'pengajuanProgramKerja.penawaranProgramKerja'
     * pada Realisasi.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applyPelaksanaanVia(Builder $query, string $relationPath): Builder
    {
        return static::batasiVia($query, $relationPath, static::tahunPelaksanaanIds());
    }

    /**
     * Ringkasan konteks siap tampil, mis. "RENSTRA 2025-2029 — TA 2026".
     */
    public static function label(): ?string
    {
        if (! static::siap()) {
            return null;
        }

        return static::kelompokAcuan()->name.' — '.static::tahunBerjalan()->name;
    }

    /**
     * Ringkasan slot perencanaan, mis. "RENSTRA 2025-2029 — TA 2027". Null bila
     * belum ada tahun yang direncanakan.
     */
    public static function labelPerencanaan(): ?string
    {
        $tahunKerja = static::tahunPerencanaan();

        if ($tahunKerja === null) {
            return null;
        }

        $kelompokAcuan = $tahunKerja->kelompokAcuan;

        return $kelompokAcuan === null
            ? $tahunKerja->name
            : $kelompokAcuan->name.' — '.$tahunKerja->name;
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<int, int>  $tahunKerjaIds
     */
    protected static function batasi(Builder $query, string $column, array $tahunKerjaIds): Builder
    {
        if ($tahunKerjaIds === []) {
            return static::tanpaPenghuni($query);
        }

        return $query->whereIn($column, $tahunKerjaIds);
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<int, int>  $tahunKerjaIds
     */
    protected static function batasiVia(Builder $query, string $relationPath, array $tahunKerjaIds): Builder
    {
        if ($tahunKerjaIds === []) {
            return static::tanpaPenghuni($query);
        }

        return $query->whereHas(
            $relationPath,
            fn (Builder $query): Builder => $query->whereIn('tahun_kerja_id', $tahunKerjaIds),
        );
    }

    /**
     * Perlakuan untuk fase yang sedang tidak punya penghuni. Pada sistem yang sudah
     * berjalan ini berarti tahun kerjanya baru diakhiri, sehingga menunya wajib
     * kosong; pada instalasi baru penyaringan dilewati agar konteks pertama masih
     * bisa dibentuk.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    protected static function tanpaPenghuni(Builder $query): Builder
    {
        return static::pernahDikonfigurasi()
            ? $query->whereRaw('1 = 0')
            : $query;
    }
}
