<?php

namespace App\Services\Notifikasi;

use App\Enums\EnumRole;
use App\Models\Pemasukan;
use App\Models\PengajuanProgramKerja;
use App\Models\RealisasiProgramKerja;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Aturan umum penentu siapa yang berhak menerima notifikasi verifikasi. Belum ada
 * mekanisme khusus pemetaan verifikator, sehingga kelas ini menjadi acuan tunggal:
 *
 * - Saat sebuah pengajuan/realisasi butuh diverifikasi (diajukan / diajukan kembali),
 *   penerimanya adalah pemegang peran verifikator tahap awal tiap kelompok verifikasi.
 * - Saat verifikator meminta revisi, penerimanya adalah pengaju asli record tersebut.
 *
 * Pemetaan peran verifikator ditaruh terpusat di sini agar mudah disesuaikan bila
 * alur/kelompok verifikasi berubah.
 */
class AturanPenerimaNotifikasi
{
    /**
     * Peran yang menerima notifikasi "perlu diverifikasi" pada tahap awal tiap
     * kelompok verifikasi. Pengajuan maupun realisasi yang diajukan/diajukan kembali
     * selalu masuk ke tahap awal kelompoknya, sehingga hanya peran ini yang relevan.
     */
    public const PERAN_VERIFIKATOR_AWAL_PENGAJUAN = EnumRole::Rektor;

    public const PERAN_VERIFIKATOR_AWAL_REALISASI = EnumRole::Rektor;

    public const PERAN_VERIFIKATOR_AWAL_PEMASUKAN = EnumRole::WakilRektor;

    /**
     * Verifikator kelompok Verifikasi Pengajuan.
     *
     * @return Collection<int, User>
     */
    public function verifikatorPengajuan(): Collection
    {
        return $this->penggunaBerperan(self::PERAN_VERIFIKATOR_AWAL_PENGAJUAN);
    }

    /**
     * Verifikator tahap awal kelompok Verifikasi Realisasi.
     *
     * @return Collection<int, User>
     */
    public function verifikatorRealisasi(): Collection
    {
        return $this->penggunaBerperan(self::PERAN_VERIFIKATOR_AWAL_REALISASI);
    }

    /**
     * Verifikator tahap awal kelompok Verifikasi Pemasukan.
     *
     * @return Collection<int, User>
     */
    public function verifikatorPemasukan(): Collection
    {
        return $this->penggunaBerperan(self::PERAN_VERIFIKATOR_AWAL_PEMASUKAN);
    }

    /**
     * Pencatat sebuah pemasukan unit, yang bertanggung jawab memperbaikinya bila
     * diminta revisi maupun mengunggah bukti tanda terimanya.
     *
     * @return Collection<int, User>
     */
    public function pengajuPemasukan(Pemasukan $pemasukan): Collection
    {
        return $this->penggunaAktif($pemasukan->user_id);
    }

    /**
     * Pengaju asli sebuah pengajuan program kerja.
     *
     * @return Collection<int, User>
     */
    public function pengajuPengajuan(PengajuanProgramKerja $pengajuan): Collection
    {
        return $this->penggunaAktif($pengajuan->user_id);
    }

    /**
     * Pengaju asli sebuah realisasi, yakni pembuat pengajuan induknya.
     *
     * @return Collection<int, User>
     */
    public function pengajuRealisasi(RealisasiProgramKerja $realisasi): Collection
    {
        return $this->penggunaAktif($realisasi->pengajuanProgramKerja?->user_id);
    }

    /**
     * Seluruh pengguna aktif yang memegang sebuah peran.
     *
     * @return Collection<int, User>
     */
    private function penggunaBerperan(EnumRole $role): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('name', $role->value))
            ->get();
    }

    /**
     * Satu pengguna aktif berdasarkan id, atau koleksi kosong bila tidak ada.
     *
     * @return Collection<int, User>
     */
    private function penggunaAktif(?int $userId): Collection
    {
        if ($userId === null) {
            return new Collection;
        }

        return User::query()
            ->where('is_active', true)
            ->whereKey($userId)
            ->get();
    }
}
