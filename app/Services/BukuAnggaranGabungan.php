<?php

namespace App\Services;

use App\Enums\EnumJenisMutasiAnggaran;
use App\Models\TahunKerja;
use Illuminate\Support\Collection;

/**
 * Buku Anggaran keseluruhan: mutasi anggaran beberapa unit kerja dilebur menjadi satu
 * buku, dengan saldo berjalan tunggal.
 *
 * Pagu seluruh unit kerja menjadi saldo pembuka, lalu saldo itu berkurang setiap kali
 * anggaran dicairkan (dan bertambah kembali saat sisa anggaran dikembalikan) — jadi
 * saldo di sini adalah sisa total pagu anggaran, bukan saldo satu unit kerja.
 *
 * Barisnya dirakit ulang dari {@see BukuAnggaran} tiap unit, bukan dari query baru,
 * sehingga isinya persis sama dengan membuka buku unit itu satu per satu.
 */
class BukuAnggaranGabungan
{
    /**
     * @var Collection<int, BukuAnggaran>|null Buku per unit, dimemoisasi karena halaman
     *                                         membaca mutasi dan ringkasan sekaligus.
     */
    private ?Collection $buku = null;

    /**
     * @var Collection<int, MutasiAnggaran>|null
     */
    private ?Collection $mutasi = null;

    /**
     * @param  array<int, string>  $unitKerja  Unit kerja yang dibaca, id => nama, sudah terurut nama.
     */
    public function __construct(
        private readonly array $unitKerja,
        private readonly ?TahunKerja $tahunKerja = null,
    ) {}

    /**
     * @param  array<int, string>  $unitKerja
     */
    public static function untukUnits(array $unitKerja, ?TahunKerja $tahunKerja = null): self
    {
        return new self($unitKerja, $tahunKerja);
    }

    /**
     * @return array<int, string>
     */
    public function unitKerja(): array
    {
        return $this->unitKerja;
    }

    /**
     * Seluruh baris buku semua unit dengan saldo berjalan gabungan.
     *
     * @return Collection<int, MutasiAnggaran>
     */
    public function mutasi(): Collection
    {
        if ($this->mutasi !== null) {
            return $this->mutasi;
        }

        $baris = $this->buku()
            ->reduce(
                fn (Collection $baris, BukuAnggaran $buku): Collection => $baris->concat($buku->mutasi()),
                collect(),
            )
            ->sortBy(fn (MutasiAnggaran $mutasi): string => $this->kunciUrutan($mutasi))
            ->values();

        $saldo = 0.0;

        return $this->mutasi = $baris->map(function (MutasiAnggaran $mutasi) use (&$saldo): MutasiAnggaran {
            $saldo += $mutasi->pengaruhSaldo();

            return $mutasi->denganSaldo($saldo);
        });
    }

    /**
     * Ringkasan gabungan: penjumlahan ringkasan tiap unit.
     *
     * @return array{kredit: float, debit: float, pemasukan: float, saldo: float, pagu: float}
     */
    public function ringkasan(): array
    {
        $ringkasan = $this->buku()->map(fn (BukuAnggaran $buku): array => $buku->ringkasan());

        return [
            'kredit' => (float) $ringkasan->sum('kredit'),
            'debit' => (float) $ringkasan->sum('debit'),
            'pemasukan' => (float) $ringkasan->sum('pemasukan'),
            'saldo' => (float) $ringkasan->sum('saldo'),
            'pagu' => (float) $ringkasan->sum('pagu'),
        ];
    }

    /**
     * Kunci pengurutan baris: seluruh pagu menjadi baris pembuka (urut nama unit kerja)
     * karena bersama-sama membentuk saldo awal, sisanya menurut tanggal lintas unit,
     * lalu kunci baris agar urutannya tetap sama antar render.
     */
    protected function kunciUrutan(MutasiAnggaran $mutasi): string
    {
        if ($mutasi->jenis === EnumJenisMutasiAnggaran::PaguDitetapkan) {
            return sprintf('0-%012d-%s', $this->urutanUnitKerja($mutasi->unitKerjaId), $mutasi->kunci());
        }

        return sprintf('1-%012d-%s', $mutasi->tanggal->getTimestamp(), $mutasi->kunci());
    }

    /**
     * Posisi unit kerja pada daftar yang dibaca (daftarnya sudah terurut nama).
     */
    protected function urutanUnitKerja(?int $unitKerjaId): int
    {
        $posisi = array_search($unitKerjaId, array_keys($this->unitKerja), true);

        return $posisi === false ? PHP_INT_MAX : $posisi;
    }

    /**
     * @return Collection<int, BukuAnggaran>
     */
    protected function buku(): Collection
    {
        return $this->buku ??= collect(array_keys($this->unitKerja))
            ->map(fn (int|string $unitKerjaId): BukuAnggaran => BukuAnggaran::untukUnit(
                (int) $unitKerjaId,
                $this->tahunKerja,
            ));
    }
}
