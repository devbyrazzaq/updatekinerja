<?php

namespace App\Exports;

use App\Enums\EnumFormatKolom;
use App\Enums\EnumSumberReferensiProgramKerja;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\RealisasiProgramKerja;
use App\Services\KodeReferensiProgramKerja;
use Illuminate\Support\Collection;

/**
 * Lembar referensi kode program kerja untuk berkas template impor capaian.
 *
 * Capaian yang diimpor akan tersimpan sebagai realisasi program kerja, dan realisasi
 * selalu menempel pada satu pengajuan program kerja. Nama program kerja tidak cukup
 * untuk merujuknya — nama yang sama bisa dipakai beberapa unit kerja, bahkan diajukan
 * lebih dari sekali oleh unit yang sama — sehingga rujukan yang dipakai berkas impor
 * adalah kode di lembar ini, yaitu id pengajuan program kerjanya.
 *
 * Daftarnya boleh berangkat dari dua sisi, dipilih saat mengunduh template
 * ({@see EnumSumberReferensiProgramKerja}): dari pengajuan yang sudah diterima, atau
 * dari katalog Daftar Program Kerja sehingga program kerja yang belum pernah diajukan
 * ikut terlihat.
 *
 * Bentuk kodenya mengikuti sisi yang dipilih ({@see KodeReferensiProgramKerja}): dari
 * pengajuan berupa angka polos, dari Daftar Program Kerja berawalan `PK-`. Keduanya
 * sama-sama siap diimpor — program kerja yang belum pernah diajukan pun tetap berkode,
 * karena pengajuannya dibuatkan saat impor berjalan.
 *
 * Kolom capaian terakhir dan jumlah realisasi ikut ditampilkan supaya pengisi berkas
 * tahu dari angka berapa capaian barunya harus naik.
 */
class ReferensiProgramKerjaExport extends Export
{
    protected const STATUS_SIAP = 'Sudah diajukan';

    protected const STATUS_BELUM = 'Belum diajukan';

    protected const TANPA_PENGAJUAN = 'Dibuatkan saat impor';

    /**
     * @param  array<int, int>  $unitKerjaIds  Unit kerja yang boleh diakses pengguna.
     */
    public function __construct(
        protected array $unitKerjaIds,
        protected ?int $tahunKerjaId,
        protected ?string $namaTahunKerja = null,
        protected EnumSumberReferensiProgramKerja $sumber = EnumSumberReferensiProgramKerja::Pengajuan,
    ) {}

    public function filename(): string
    {
        return 'referensi-program-kerja';
    }

    public function title(): string
    {
        return 'Referensi Program Kerja';
    }

    public function subtitle(): ?string
    {
        $tambahan = $this->dariDaftarProgramKerja()
            ? ' Kode berawalan "'.KodeReferensiProgramKerja::AWALAN_DAFTAR.'" menunjuk program kerja pada Daftar Program Kerja; '
                .'bila program kerja itu belum pernah diajukan, pengajuannya dibuatkan otomatis saat impor berjalan.'
            : '';

        return 'Salin nilai kolom kode_program_kerja ke lembar isian — kode inilah rujukan '
            .'realisasi capaian yang akan dibuat. Nama program kerja tidak dipakai karena bisa kembar antar unit kerja. '
            .'Daftar ini diambil dari '.$this->sumber->getLabel().': '.$this->sumber->keterangan().$tambahan
            .($this->namaTahunKerja !== null ? ' Khusus tahun kerja '.$this->namaTahunKerja.'.' : '');
    }

    public function headings(): array
    {
        return [
            'kode_program_kerja',
            'program_kerja',
            'unit_kerja',
            'bidang',
            'program_induk',
            'tahun_kerja',
            'status_pengajuan',
            // Hanya berguna saat berangkat dari katalog: dari sisi pengajuan, kode
            // pengajuannya sudah menjadi kolom pertama.
            ...($this->dariDaftarProgramKerja() ? ['kode_pengajuan'] : []),
            'alokasi_anggaran',
            'jumlah_realisasi',
            'capaian_terakhir',
        ];
    }

    public function columnLabels(): array
    {
        return [
            'kode_program_kerja' => 'Kode Program Kerja',
            'program_kerja' => 'Program Kerja',
            'unit_kerja' => 'Unit Kerja',
            'bidang' => 'Bidang',
            'program_induk' => 'Program Induk',
            'tahun_kerja' => 'Tahun Kerja',
            'status_pengajuan' => 'Status Pengajuan',
            'kode_pengajuan' => 'Kode Pengajuan',
            'alokasi_anggaran' => 'Anggaran Diajukan',
            'jumlah_realisasi' => 'Realisasi Tercatat',
            'capaian_terakhir' => 'Capaian Terakhir',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'kode_program_kerja' => EnumFormatKolom::Teks,
            'kode_pengajuan' => EnumFormatKolom::Teks,
            'jumlah_realisasi' => EnumFormatKolom::Angka,
            'capaian_terakhir' => EnumFormatKolom::Persen,
        ];
    }

    public function rows(): iterable
    {
        return $this->dariDaftarProgramKerja()
            ? $this->barisDaftarProgramKerja()
            : $this->barisPengajuan();
    }

    protected function dariDaftarProgramKerja(): bool
    {
        return $this->sumber === EnumSumberReferensiProgramKerja::DaftarProgramKerja;
    }

    /**
     * Baris dari sisi pengajuan: satu baris per pengajuan, berkode id pengajuannya.
     *
     * @return Collection<int, array<int, mixed>>
     */
    protected function barisPengajuan(): Collection
    {
        return $this->pengajuans()->map(fn (PengajuanProgramKerja $pengajuan): array => [
            KodeReferensiProgramKerja::pengajuan((int) $pengajuan->getKey()),
            $pengajuan->penawaranProgramKerja?->name,
            $pengajuan->unitKerja?->name,
            $pengajuan->penawaranProgramKerja?->bidang?->name,
            $pengajuan->penawaranProgramKerja?->program?->name,
            $pengajuan->penawaranProgramKerja?->tahunKerja?->name,
            self::STATUS_SIAP,
            (float) $pengajuan->alokasi_anggaran,
            $pengajuan->realisasi_program_kerjas_count,
            RealisasiProgramKerja::persentaseKetercapaianTertinggi((int) $pengajuan->getKey()),
        ]);
    }

    /**
     * Baris dari sisi katalog Daftar Program Kerja: satu baris per program kerja yang
     * ditawarkan, seluruhnya berkode `PK-<id>` — termasuk yang belum pernah diajukan,
     * karena pengajuannya dibuatkan saat impor berjalan.
     *
     * Angka anggaran, jumlah realisasi, dan capaian terakhirnya dirangkum dari seluruh
     * pengajuan program kerja itu, sehingga tetap terbaca sebagai satu program kerja
     * meski pernah diajukan lebih dari sekali.
     *
     * @return Collection<int, array<int, mixed>>
     */
    protected function barisDaftarProgramKerja(): Collection
    {
        $pengajuanPerPenawaran = $this->pengajuans()->groupBy('penawaran_program_kerja_id');

        return $this->penawarans()->map(function (PenawaranProgramKerja $penawaran) use ($pengajuanPerPenawaran): array {
            /** @var Collection<int, PengajuanProgramKerja> $pengajuans */
            $pengajuans = $pengajuanPerPenawaran->get($penawaran->getKey()) ?? collect();

            return [
                KodeReferensiProgramKerja::daftarProgramKerja((int) $penawaran->getKey()),
                $penawaran->name,
                $penawaran->unitKerja?->name,
                $penawaran->bidang?->name,
                $penawaran->program?->name,
                $penawaran->tahunKerja?->name,
                $pengajuans->isEmpty() ? self::STATUS_BELUM : self::STATUS_SIAP,
                $pengajuans->isEmpty()
                    ? self::TANPA_PENGAJUAN
                    : $pengajuans->map(fn (PengajuanProgramKerja $pengajuan): string => KodeReferensiProgramKerja::pengajuan((int) $pengajuan->getKey()))->implode(', '),
                (float) $pengajuans->sum(fn (PengajuanProgramKerja $pengajuan): float => (float) $pengajuan->alokasi_anggaran),
                (int) $pengajuans->sum('realisasi_program_kerjas_count'),
                (int) $pengajuans->max(fn (PengajuanProgramKerja $pengajuan): int => RealisasiProgramKerja::persentaseKetercapaianTertinggi((int) $pengajuan->getKey())),
            ];
        });
    }

    /**
     * @return Collection<int, PengajuanProgramKerja>
     */
    protected function pengajuans(): Collection
    {
        if ($this->tahunKerjaId === null || $this->unitKerjaIds === []) {
            return collect();
        }

        return PengajuanProgramKerja::query()
            ->dapatDicatatCapaiannya($this->tahunKerjaId, $this->unitKerjaIds)
            ->with([
                'unitKerja:id,name',
                'penawaranProgramKerja:id,name,bidang_id,program_id,tahun_kerja_id',
                'penawaranProgramKerja.bidang:id,name',
                'penawaranProgramKerja.program:id,name',
                'penawaranProgramKerja.tahunKerja:id,name',
            ])
            ->withCount('realisasiProgramKerjas')
            ->get()
            ->sortBy([
                fn (PengajuanProgramKerja $a, PengajuanProgramKerja $b): int => strcmp((string) $a->unitKerja?->name, (string) $b->unitKerja?->name),
                fn (PengajuanProgramKerja $a, PengajuanProgramKerja $b): int => strcmp((string) $a->penawaranProgramKerja?->name, (string) $b->penawaranProgramKerja?->name),
            ])
            ->values();
    }

    /**
     * Katalog program kerja aktif yang ditawarkan ke unit kerja pada tahun kerja ini —
     * isi menu Daftar Program Kerja.
     *
     * @return Collection<int, PenawaranProgramKerja>
     */
    protected function penawarans(): Collection
    {
        if ($this->tahunKerjaId === null || $this->unitKerjaIds === []) {
            return collect();
        }

        return PenawaranProgramKerja::query()
            ->where('tahun_kerja_id', $this->tahunKerjaId)
            ->whereIn('unit_kerja_id', array_values(array_unique(array_map('intval', $this->unitKerjaIds))))
            ->where('is_active', true)
            ->with([
                'unitKerja:id,name',
                'bidang:id,name',
                'program:id,name',
                'tahunKerja:id,name',
            ])
            ->get()
            ->sortBy([
                fn (PenawaranProgramKerja $a, PenawaranProgramKerja $b): int => strcmp((string) $a->unitKerja?->name, (string) $b->unitKerja?->name),
                fn (PenawaranProgramKerja $a, PenawaranProgramKerja $b): int => strcmp((string) $a->name, (string) $b->name),
            ])
            ->values();
    }
}
