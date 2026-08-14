<?php

namespace App\Services;

use App\Enums\EnumStatusPenyelesaianAnggaran;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Models\RealisasiProgramKerja;
use App\Models\TahunKerja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Satu-satunya jalur perpindahan status tahun kerja. Memusatkan perpindahan di
 * sini menjaga invariant dua slot — paling banyak satu tahun Berjalan dan satu
 * tahun Perencanaan — sekaligus memastikan tahun lama tidak pernah dikunci selagi
 * masih ada realisasi atau selisih anggaran yang menggantung.
 *
 * Mengakhiri tahun kerja ditempuh dua langkah yang sengaja dipisah:
 * {@see akhiriTahunKerja()} menyetop pengajuan dan realisasi baru, lalu
 * {@see kunciTahunKerja()} menjadikannya hanya-baca setelah seluruh tunggakannya
 * tuntas. Langkah pertama masih bisa ditarik kembali lewat {@see batalkanPenutupan()};
 * langkah kedua tidak.
 */
class TransisiTahunKerja
{
    /**
     * Menempatkan tahun kerja pada slot perencanaan.
     *
     * @throws RuntimeException bila slot perencanaan sudah terisi tahun lain
     */
    public function tetapkanPerencanaan(TahunKerja $tahunKerja): TahunKerja
    {
        if ($tahunKerja->status === EnumStatusTahunKerja::Perencanaan) {
            return $tahunKerja;
        }

        if ($tahunKerja->status === EnumStatusTahunKerja::Berjalan) {
            throw new RuntimeException("{$tahunKerja->name} sedang berjalan, sehingga tidak bisa dikembalikan ke tahap perencanaan.");
        }

        $pemegang = TahunKerja::perencanaan();

        if ($pemegang instanceof TahunKerja && ! $pemegang->is($tahunKerja)) {
            throw new RuntimeException("{$pemegang->name} sedang menempati slot perencanaan. Jalankan atau lepaskan tahun tersebut lebih dulu.");
        }

        $tahunKerja->update(['status' => EnumStatusTahunKerja::Perencanaan]);

        return $tahunKerja;
    }

    /**
     * Menempatkan tahun kerja pada slot berjalan. Tahun yang sedang berjalan
     * sebelumnya otomatis bergeser ke Penutupan agar realisasinya masih bisa
     * dituntaskan.
     *
     * Tahun berstatus Selesai tetap boleh dijalankan: status itu menampung tahun
     * kerja yang tidak sedang menempati slot mana pun — termasuk tahun yang baru
     * dibuat dan belum pernah dijalankan sama sekali.
     */
    public function mulaiTahunKerja(TahunKerja $tahunKerja): TahunKerja
    {
        if ($tahunKerja->status === EnumStatusTahunKerja::Berjalan) {
            return $tahunKerja;
        }

        return DB::transaction(function () use ($tahunKerja): TahunKerja {
            $sebelumnya = TahunKerja::berjalan();

            if ($sebelumnya instanceof TahunKerja && ! $sebelumnya->is($tahunKerja)) {
                $this->akhiriTahunKerja($sebelumnya);
            }

            $tahunKerja->update(['status' => EnumStatusTahunKerja::Berjalan]);

            return $tahunKerja;
        });
    }

    /**
     * Mengakhiri tahun kerja yang sedang berjalan tanpa menunggu tahun pengganti:
     * pengajuan dan realisasi baru ditutup, sementara realisasi yang terlanjur
     * berjalan tetap boleh dituntaskan lewat halaman Penyelesaian Tahun Lalu.
     *
     * Tidak ada data yang dihapus, hanya statusnya yang bergeser — karena itu
     * langkah ini masih bisa ditarik kembali lewat {@see batalkanPenutupan()}.
     *
     * @throws RuntimeException bila tahun kerja tidak sedang berjalan
     */
    public function akhiriTahunKerja(TahunKerja $tahunKerja): TahunKerja
    {
        if ($tahunKerja->status === EnumStatusTahunKerja::Penutupan) {
            return $tahunKerja;
        }

        if ($tahunKerja->status !== EnumStatusTahunKerja::Berjalan) {
            throw new RuntimeException(
                "{$tahunKerja->name} berstatus {$tahunKerja->status->getLabel()}, sehingga tidak ada yang perlu diakhiri. Hanya tahun kerja yang sedang berjalan yang bisa diakhiri."
            );
        }

        $tahunKerja->update([
            'status' => EnumStatusTahunKerja::Penutupan,
            'ditutup_pada' => now(),
            'ditutup_oleh_id' => auth()->id(),
        ]);

        return $tahunKerja;
    }

    /**
     * Mengembalikan tahun kerja yang terlanjur diakhiri ke slot berjalan. Hanya
     * mungkin selama slot tersebut belum ditempati tahun lain.
     *
     * @throws RuntimeException bila tahun kerja tidak berstatus Penutupan atau slot
     *                          berjalan sudah terisi
     */
    public function batalkanPenutupan(TahunKerja $tahunKerja): TahunKerja
    {
        if ($tahunKerja->status !== EnumStatusTahunKerja::Penutupan) {
            throw new RuntimeException(
                "{$tahunKerja->name} berstatus {$tahunKerja->status->getLabel()}, bukan Penutupan, sehingga penutupannya tidak bisa dibatalkan."
            );
        }

        $pemegang = TahunKerja::berjalan();

        if ($pemegang instanceof TahunKerja) {
            throw new RuntimeException(
                "{$pemegang->name} sedang menempati slot berjalan. Akhiri tahun tersebut lebih dulu bila {$tahunKerja->name} hendak dijalankan kembali."
            );
        }

        $tahunKerja->update([
            'status' => EnumStatusTahunKerja::Berjalan,
            'ditutup_pada' => null,
            'ditutup_oleh_id' => null,
        ]);

        return $tahunKerja;
    }

    /**
     * Pekerjaan yang akan ikut dibekukan begitu tahun kerja diakhiri. Berbeda dengan
     * {@see penghambatPenguncian()}, daftar ini tidak pernah menghalangi: pengakhiran
     * memang boleh dilakukan selagi ada pekerjaan berjalan, dan keterangan ini hanya
     * memberi tahu apa yang berpindah ke halaman Penyelesaian Tahun Lalu.
     *
     * @return array<int, string>
     */
    public function dampakPengakhiran(TahunKerja $tahunKerja): array
    {
        $dampak = [];

        $realisasiBerjalan = $this->realisasiTahunKerja($tahunKerja)
            ->whereIn('status', array_column(EnumStatusRealisasi::berjalan(), 'value'))
            ->count();

        if ($realisasiBerjalan > 0) {
            $dampak[] = "{$realisasiBerjalan} realisasi masih berjalan dan pindah ke halaman Penyelesaian Tahun Lalu untuk dituntaskan.";
        }

        $pengajuan = $tahunKerja->pengajuanBerjalan()->count();

        if ($pengajuan > 0) {
            $dampak[] = "{$pengajuan} pengajuan program kerja tidak lagi bisa dilanjutkan menjadi realisasi baru.";
        }

        return $dampak;
    }

    /**
     * Mengunci tahun kerja menjadi read-only.
     *
     * @throws RuntimeException bila masih ada realisasi berjalan atau selisih
     *                          anggaran yang menunggu tindak lanjut Biro Keuangan
     */
    public function kunciTahunKerja(TahunKerja $tahunKerja): TahunKerja
    {
        if ($tahunKerja->status === EnumStatusTahunKerja::Selesai) {
            return $tahunKerja;
        }

        $penghambat = $this->penghambatPenguncian($tahunKerja);

        if ($penghambat !== []) {
            throw new RuntimeException(
                "{$tahunKerja->name} belum bisa dikunci: ".implode(' ', $penghambat)
            );
        }

        $tahunKerja->update([
            'status' => EnumStatusTahunKerja::Selesai,
            'dikunci_pada' => now(),
            'dikunci_oleh_id' => auth()->id(),
        ]);

        return $tahunKerja;
    }

    /**
     * Alasan tahun kerja belum bisa dikunci, siap ditampilkan pada modal konfirmasi.
     * Kosong berarti tahun kerja aman dikunci.
     *
     * @return array<int, string>
     */
    public function penghambatPenguncian(TahunKerja $tahunKerja): array
    {
        $penghambat = [];

        $realisasiBerjalan = $this->realisasiTahunKerja($tahunKerja)
            ->whereIn('status', array_column(EnumStatusRealisasi::berjalan(), 'value'))
            ->count();

        if ($realisasiBerjalan > 0) {
            $penghambat[] = "{$realisasiBerjalan} realisasi masih berjalan.";
        }

        $selisihMenunggu = $this->realisasiTahunKerja($tahunKerja)
            ->where('status_penyelesaian_anggaran', EnumStatusPenyelesaianAnggaran::Menunggu)
            ->count();

        if ($selisihMenunggu > 0) {
            $penghambat[] = "{$selisihMenunggu} laporan masih menunggu penyelesaian selisih anggaran dari Biro Keuangan.";
        }

        return $penghambat;
    }

    /**
     * Seluruh realisasi yang bernaung di bawah satu tahun kerja.
     *
     * @return Builder<RealisasiProgramKerja>
     */
    protected function realisasiTahunKerja(TahunKerja $tahunKerja): Builder
    {
        return RealisasiProgramKerja::query()->whereHas(
            'pengajuanProgramKerja.penawaranProgramKerja',
            fn (Builder $query): Builder => $query->where('tahun_kerja_id', $tahunKerja->getKey()),
        );
    }
}
