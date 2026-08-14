<?php

namespace App\Services;

use App\Enums\EnumJenisMutasiAnggaran;
use App\Models\PengajuanProgramKerja;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Satu baris Buku Anggaran: peristiwa bertanggal yang menambah (kredit), mengurangi
 * (debit), atau sekadar menambah catatan pemasukan pada anggaran unit kerja, beserta
 * saldo berjalan setelahnya.
 *
 * Dibentuk oleh {@see BukuAnggaran} dari data yang sudah ada; saldo diisi belakangan
 * lewat {@see denganSaldo()} setelah seluruh baris terurut.
 */
readonly class MutasiAnggaran
{
    public function __construct(
        public Carbon $tanggal,
        public EnumJenisMutasiAnggaran $jenis,
        public string $keterangan,
        public float $nominal,
        public string $referensiType,
        public int $referensiId,
        public ?int $pengajuanId = null,
        public ?string $url = null,
        public float $saldo = 0.0,
        public ?int $unitKerjaId = null,
    ) {}

    /**
     * Baris yang sama dengan saldo berjalan terisi.
     */
    public function denganSaldo(float $saldo): self
    {
        return new self(
            $this->tanggal,
            $this->jenis,
            $this->keterangan,
            $this->nominal,
            $this->referensiType,
            $this->referensiId,
            $this->pengajuanId,
            $this->url,
            $saldo,
            $this->unitKerjaId,
        );
    }

    public function debit(): float
    {
        return (! $this->jenis->adalahKredit() && ! $this->jenis->adalahPemasukan())
            ? $this->nominal
            : 0.0;
    }

    public function kredit(): float
    {
        return ($this->jenis->adalahKredit() && ! $this->jenis->adalahPemasukan())
            ? $this->nominal
            : 0.0;
    }

    public function pemasukan(): float
    {
        return $this->jenis->adalahPemasukan() ? $this->nominal : 0.0;
    }

    /**
     * Pengaruh baris ini terhadap saldo anggaran; pemasukan tidak menggerakkannya.
     */
    public function pengaruhSaldo(): float
    {
        return $this->kredit() - $this->debit();
    }

    /**
     * Baris ini berasal dari record tersebut, atau dari realisasi/pemasukan yang
     * bernaung di bawah pengajuan tersebut.
     */
    public function merujuk(Model $record): bool
    {
        if ($this->referensiType === $record::class && $this->referensiId === (int) $record->getKey()) {
            return true;
        }

        return $record instanceof PengajuanProgramKerja
            && $this->pengajuanId === (int) $record->getKey();
    }

    /**
     * Kunci baris yang stabil antar render, dipakai tabel Filament sebagai id record.
     */
    public function kunci(): string
    {
        return $this->jenis->value.'-'.$this->referensiId;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kunci' => $this->kunci(),
            'unit_kerja_id' => $this->unitKerjaId,
            'tanggal' => $this->tanggal,
            'jenis' => $this->jenis,
            'keterangan' => $this->keterangan,
            'debit' => $this->debit(),
            'kredit' => $this->kredit(),
            'saldo' => $this->saldo,
            'pemasukan' => $this->pemasukan(),
            'url' => $this->url,
        ];
    }
}
