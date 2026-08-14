<?php

namespace App\Services;

use App\Enums\EnumJenisMutasiAnggaran;
use App\Enums\EnumStatusAnggaran;
use App\Enums\EnumStatusPemasukan;
use App\Filament\Resources\PaguAnggarans\PaguAnggaranResource;
use App\Filament\Resources\Pemasukans\PemasukanResource;
use App\Filament\Resources\RealisasiProgramKerjas\RealisasiProgramKerjaResource;
use App\Models\PaguAnggaran;
use App\Models\Pemasukan;
use App\Models\PengajuanProgramKerja;
use App\Models\RealisasiProgramKerja;
use App\Models\TahunKerja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Buku Anggaran satu unit kerja pada satu tahun kerja: daftar mutasi anggaran urut
 * waktu beserta saldo berjalannya, bergaya buku kas (kredit menambah, debit
 * mengurangi anggaran yang bisa dipakai).
 *
 * Barisnya diturunkan saat dibaca dari data yang sudah ada — pagu, pencairan
 * realisasi, penyelesaian selisih anggaran, dan pemasukan — bukan dari tabel jurnal
 * tersendiri, sehingga mustahil melenceng dari data aslinya.
 *
 * Bukunya berbasis kas: anggaran dianggap keluar saat benar-benar dicairkan
 * (`dicairkan_at`), sehingga pengajuan yang masih draf maupun yang masih diverifikasi
 * belum tampil. Karena itu saldo buku ini berbeda dari kartu "Sisa Anggaran" pada form
 * pengajuan, yang mengikat anggaran sejak pengajuan dibuat.
 */
class BukuAnggaran
{
    /**
     * @var Collection<int, MutasiAnggaran>|null
     */
    private ?Collection $mutasi = null;

    private ?TahunKerja $tahunKerja;

    public function __construct(
        public readonly int $unitKerjaId,
        ?TahunKerja $tahunKerja = null,
    ) {
        $this->tahunKerja = $tahunKerja ?? KonteksProgramKerja::tahunBerjalan();
    }

    public static function untukUnit(int $unitKerjaId, ?TahunKerja $tahunKerja = null): self
    {
        return new self($unitKerjaId, $tahunKerja);
    }

    /**
     * Buku milik unit kerja & tahun kerja sebuah record, lalu disaring hanya pada baris
     * yang berasal dari record itu. Untuk pengajuan, baris realisasi dan pemasukan yang
     * bernaung di bawahnya ikut terbawa.
     *
     * @return Collection<int, MutasiAnggaran>
     */
    public static function untukRecord(RealisasiProgramKerja|PengajuanProgramKerja $record): Collection
    {
        $pengajuan = $record instanceof RealisasiProgramKerja
            ? $record->pengajuanProgramKerja
            : $record;

        $unitKerjaId = $pengajuan?->unit_kerja_id;

        if ($unitKerjaId === null) {
            return collect();
        }

        return self::untukUnit(
            (int) $unitKerjaId,
            $pengajuan?->penawaranProgramKerja?->tahunKerja,
        )->mutasi()->filter(fn (MutasiAnggaran $mutasi): bool => $mutasi->merujuk($record))->values();
    }

    public function tahunKerja(): ?TahunKerja
    {
        return $this->tahunKerja;
    }

    /**
     * Seluruh baris buku, terurut menurut tanggal dan sudah terisi saldo berjalan.
     * Kosong bila tahun kerja aktif belum ditetapkan.
     *
     * @return Collection<int, MutasiAnggaran>
     */
    public function mutasi(): Collection
    {
        if ($this->mutasi !== null) {
            return $this->mutasi;
        }

        if ($this->tahunKerja === null) {
            return $this->mutasi = collect();
        }

        $baris = collect()
            ->concat($this->barisPagu())
            ->concat($this->barisRealisasi())
            ->concat($this->barisPemasukan())
            ->sortBy(fn (MutasiAnggaran $mutasi): string => $this->kunciUrutan($mutasi))
            ->values();

        $saldo = 0.0;

        return $this->mutasi = $baris->map(function (MutasiAnggaran $mutasi) use (&$saldo): MutasiAnggaran {
            $saldo += $mutasi->pengaruhSaldo();

            return $mutasi->denganSaldo($saldo);
        });
    }

    /**
     * Kunci pengurutan baris buku: pagu selalu menjadi baris pembuka karena ia saldo
     * awal, bukan mutasi yang bersaing urutan; sisanya menurut tanggal, lalu kunci
     * baris agar urutannya tetap sama antar render.
     */
    protected function kunciUrutan(MutasiAnggaran $mutasi): string
    {
        $urutanJenis = $mutasi->jenis === EnumJenisMutasiAnggaran::PaguDitetapkan ? 0 : 1;

        return sprintf('%d-%012d-%s', $urutanJenis, $mutasi->tanggal->getTimestamp(), $mutasi->kunci());
    }

    /**
     * Ringkasan buku: total kredit, debit, pemasukan, saldo akhir, dan pagu yang
     * ditetapkan. Pagu ditampilkan terpisah karena di kolom kredit ia berbaur dengan
     * pengembalian sisa anggaran.
     *
     * @return array{kredit: float, debit: float, pemasukan: float, saldo: float, pagu: float}
     */
    public function ringkasan(): array
    {
        $mutasi = $this->mutasi();

        $kredit = (float) $mutasi->sum(fn (MutasiAnggaran $baris): float => $baris->kredit());
        $debit = (float) $mutasi->sum(fn (MutasiAnggaran $baris): float => $baris->debit());

        return [
            'kredit' => $kredit,
            'debit' => $debit,
            'pemasukan' => (float) $mutasi->sum(fn (MutasiAnggaran $baris): float => $baris->pemasukan()),
            'saldo' => $kredit - $debit,
            'pagu' => (float) $mutasi
                ->where('jenis', EnumJenisMutasiAnggaran::PaguDitetapkan)
                ->sum(fn (MutasiAnggaran $baris): float => $baris->nominal),
        ];
    }

    /**
     * Pagu anggaran unit kerja pada tahun kerja ini, menjadi saldo pembuka buku.
     * Tanggalnya mengikuti awal tahun kerja karena pagu berlaku sejak tahun kerja
     * dimulai; pagu juga tidak menyimpan riwayat perubahan, sehingga yang tampil
     * selalu nominal terkininya.
     *
     * @return Collection<int, MutasiAnggaran>
     */
    protected function barisPagu(): Collection
    {
        $pagu = PaguAnggaran::query()
            ->where('tahun_kerja_id', $this->tahunKerja->getKey())
            ->where('unit_kerja_id', $this->unitKerjaId)
            ->first();

        if ($pagu === null || (float) $pagu->amount <= 0) {
            return collect();
        }

        return collect([
            new MutasiAnggaran(
                tanggal: $this->tahunKerja->start_datetime ?? $pagu->created_at,
                jenis: EnumJenisMutasiAnggaran::PaguDitetapkan,
                keterangan: 'Pagu anggaran '.($this->tahunKerja->name ?? 'tahun kerja aktif'),
                nominal: (float) $pagu->amount,
                referensiType: PaguAnggaran::class,
                referensiId: (int) $pagu->getKey(),
                url: PaguAnggaranResource::getUrl('view', ['record' => $pagu]),
                unitKerjaId: $this->unitKerjaId,
            ),
        ]);
    }

    /**
     * Baris dari realisasi: anggaran yang dicairkan ke unit kerja, serta penyelesaian
     * selisih anggarannya (sisa dikembalikan atau kekurangan dilunasi). Selisih yang
     * masih menunggu Biro Keuangan belum menggerakkan buku.
     *
     * @return Collection<int, MutasiAnggaran>
     */
    protected function barisRealisasi(): Collection
    {
        $realisasi = RealisasiProgramKerja::query()
            ->with('pengajuanProgramKerja')
            ->whereHas('pengajuanProgramKerja', fn (Builder $query): Builder => $query
                ->where('unit_kerja_id', $this->unitKerjaId)
                ->whereHas('penawaranProgramKerja', fn (Builder $penawaran): Builder => $penawaran
                    ->where('tahun_kerja_id', $this->tahunKerja->getKey())))
            ->where(function (Builder $query): void {
                $query->whereNotNull('dicairkan_at')
                    ->orWhereNotNull('penyelesaian_anggaran_at');
            })
            ->get();

        $baris = collect();

        foreach ($realisasi as $record) {
            $kegiatan = $record->name ?? 'realisasi';
            $url = RealisasiProgramKerjaResource::getUrl('view', ['record' => $record]);

            if ($record->dicairkan_at !== null) {
                $baris->push(new MutasiAnggaran(
                    tanggal: $record->dicairkan_at,
                    jenis: EnumJenisMutasiAnggaran::AnggaranDicairkan,
                    keterangan: "Anggaran realisasi \"{$kegiatan}\" dicairkan",
                    nominal: $record->nominalDiterima(),
                    referensiType: RealisasiProgramKerja::class,
                    referensiId: (int) $record->getKey(),
                    pengajuanId: $record->pengajuan_program_kerja_id,
                    url: $url,
                    unitKerjaId: $this->unitKerjaId,
                ));
            }

            $jenisPenyelesaian = $this->jenisPenyelesaian($record);

            if ($jenisPenyelesaian !== null) {
                $baris->push(new MutasiAnggaran(
                    tanggal: $record->penyelesaian_anggaran_at,
                    jenis: $jenisPenyelesaian,
                    keterangan: $jenisPenyelesaian === EnumJenisMutasiAnggaran::SisaDikembalikan
                        ? "Sisa anggaran realisasi \"{$kegiatan}\" dikembalikan"
                        : "Kekurangan anggaran realisasi \"{$kegiatan}\" dilunasi",
                    nominal: (float) ($record->nominal_selisih_anggaran ?? 0),
                    referensiType: RealisasiProgramKerja::class,
                    referensiId: (int) $record->getKey(),
                    pengajuanId: $record->pengajuan_program_kerja_id,
                    url: $url,
                    unitKerjaId: $this->unitKerjaId,
                ));
            }
        }

        return $baris;
    }

    /**
     * Jenis mutasi dari penyelesaian selisih anggaran sebuah realisasi, null bila
     * selisihnya belum dituntaskan atau tidak ada selisih sama sekali.
     */
    protected function jenisPenyelesaian(RealisasiProgramKerja $record): ?EnumJenisMutasiAnggaran
    {
        if ($record->penyelesaian_anggaran_at === null
            || ! ($record->status_penyelesaian_anggaran?->sudahSelesai() ?? false)
            || (float) ($record->nominal_selisih_anggaran ?? 0) <= 0) {
            return null;
        }

        return match ($record->status_anggaran) {
            EnumStatusAnggaran::Sisa => EnumJenisMutasiAnggaran::SisaDikembalikan,
            EnumStatusAnggaran::Kurang => EnumJenisMutasiAnggaran::KekuranganDilunasi,
            default => null,
        };
    }

    /**
     * Pemasukan unit kerja pada tahun kerja ini. Tahun kerjanya diambil dari pengajuan
     * atau realisasi yang ditautkan; pemasukan tanpa tautan dinilai dari tanggal
     * pelaksanaannya terhadap rentang tahun kerja.
     *
     * @return Collection<int, MutasiAnggaran>
     */
    protected function barisPemasukan(): Collection
    {
        $tahunKerjaId = $this->tahunKerja->getKey();

        return Pemasukan::query()
            ->where('unit_kerja_id', $this->unitKerjaId)
            // Hanya pemasukan yang sudah melewati verifikasi tiga tahap dan dilengkapi
            // bukti tanda terima yang dihitung sebagai pemasukan sungguhan.
            ->where('status', EnumStatusPemasukan::Valid)
            ->where(function (Builder $query) use ($tahunKerjaId): void {
                $query
                    ->whereHas('pengajuanProgramKerja.penawaranProgramKerja', fn (Builder $penawaran): Builder => $penawaran
                        ->where('tahun_kerja_id', $tahunKerjaId))
                    ->orWhereHas('realisasiProgramKerja.pengajuanProgramKerja.penawaranProgramKerja', fn (Builder $penawaran): Builder => $penawaran
                        ->where('tahun_kerja_id', $tahunKerjaId))
                    ->orWhere(fn (Builder $tanpaTautan): Builder => $tanpaTautan
                        ->whereNull('pengajuan_program_kerja_id')
                        ->whereNull('realisasi_program_kerja_id')
                        ->whereBetween('tanggal_pelaksanaan', $this->rentangTahunKerja()));
            })
            ->get()
            ->map(fn (Pemasukan $pemasukan): MutasiAnggaran => new MutasiAnggaran(
                tanggal: $pemasukan->tanggal_pelaksanaan,
                jenis: EnumJenisMutasiAnggaran::Pemasukan,
                keterangan: 'Pemasukan '.$pemasukan->rincian_kegiatan,
                nominal: (float) $pemasukan->nominal_pendapatan,
                referensiType: Pemasukan::class,
                referensiId: (int) $pemasukan->getKey(),
                pengajuanId: $pemasukan->pengajuan_program_kerja_id,
                url: PemasukanResource::getUrl('view', ['record' => $pemasukan]),
                unitKerjaId: $this->unitKerjaId,
            ));
    }

    /**
     * Rentang tanggal tahun kerja untuk menilai pemasukan yang tidak tertaut pengajuan
     * maupun realisasi. Batas yang belum diisi dilebarkan agar tidak ada yang tersaring
     * keluar tanpa alasan.
     *
     * @return array{0: string, 1: string}
     */
    protected function rentangTahunKerja(): array
    {
        return [
            ($this->tahunKerja->start_datetime ?? now()->subCentury())->toDateString(),
            ($this->tahunKerja->end_datetime ?? now()->addCentury())->toDateString(),
        ];
    }
}
