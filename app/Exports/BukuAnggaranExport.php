<?php

namespace App\Exports;

use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Services\BukuAnggaran;
use App\Services\BukuAnggaranGabungan;
use App\Services\KonteksProgramKerja;
use App\Services\MutasiAnggaran;
use Illuminate\Support\Str;

/**
 * Ekspor Buku Anggaran pada tahun kerja yang sedang dibaca (bawaannya tahun kerja
 * aktif), kolomnya sejajar dengan tabel di halaman: debit, kredit, saldo berjalan, dan
 * pemasukan terpisah.
 *
 * Tanpa unit kerja tertentu, isinya buku gabungan seluruh unit kerja yang diberikan
 * (kolom unit kerja ikut disertakan) — mengikuti tampilan halaman.
 */
class BukuAnggaranExport extends Export
{
    /**
     * @param  array<int, string>  $unitKerja  Unit kerja gabungan, id => nama, dipakai saat $unitKerjaId null.
     * @param  int|null  $tahunKerjaId  Tahun kerja yang diekspor; null mengikuti tahun kerja aktif.
     */
    public function __construct(
        private readonly ?int $unitKerjaId = null,
        private readonly array $unitKerja = [],
        private readonly ?int $tahunKerjaId = null,
    ) {}

    public function filename(): string
    {
        $unit = $this->unitKerjaId !== null
            ? UnitKerja::query()->whereKey($this->unitKerjaId)->value('name')
            : null;

        $tahunKerja = $this->tahunKerja();

        return 'buku-anggaran-'
            .Str::slug($unit ?? 'semua-unit')
            .($tahunKerja !== null ? '-'.Str::slug($tahunKerja->name) : '')
            .'-'.now()->format('Y-m-d');
    }

    public function title(): string
    {
        return 'Buku Anggaran';
    }

    public function subtitle(): ?string
    {
        $unit = $this->unitKerjaId !== null
            ? UnitKerja::query()->whereKey($this->unitKerjaId)->value('name')
            : 'Seluruh Unit Kerja';

        $tahunKerja = $this->tahunKerja()?->name;

        return trim($unit.($tahunKerja !== null ? ' · '.$tahunKerja : ''));
    }

    public function headings(): array
    {
        $kolom = ['tanggal', 'jenis', 'keterangan', 'debit', 'kredit', 'saldo', 'pemasukan'];

        return $this->unitKerjaId === null
            ? ['unit_kerja', ...$kolom]
            : $kolom;
    }

    public function columnLabels(): array
    {
        return [
            'unit_kerja' => 'Unit Kerja',
            'tanggal' => 'Tanggal',
            'jenis' => 'Jenis Mutasi',
            'keterangan' => 'Keterangan',
            'debit' => 'Debit',
            'kredit' => 'Kredit',
            'saldo' => 'Saldo',
            'pemasukan' => 'Pemasukan',
        ];
    }

    public function rows(): iterable
    {
        $tahunKerja = $this->tahunKerja();

        if ($this->unitKerjaId !== null) {
            return BukuAnggaran::untukUnit($this->unitKerjaId, $tahunKerja)
                ->mutasi()
                ->map(fn (MutasiAnggaran $mutasi): array => $this->baris($mutasi));
        }

        return BukuAnggaranGabungan::untukUnits($this->unitKerja, $tahunKerja)
            ->mutasi()
            ->map(fn (MutasiAnggaran $mutasi): array => [
                $this->unitKerja[$mutasi->unitKerjaId] ?? '-',
                ...$this->baris($mutasi),
            ]);
    }

    /**
     * Tahun kerja yang diekspor; tanpa pilihan tegas, buku jatuh ke tahun kerja aktif
     * sebagaimana halamannya.
     */
    protected function tahunKerja(): ?TahunKerja
    {
        return $this->tahunKerjaId !== null
            ? TahunKerja::find($this->tahunKerjaId)
            : KonteksProgramKerja::tahunBerjalan();
    }

    /**
     * @return array<int, mixed>
     */
    protected function baris(MutasiAnggaran $mutasi): array
    {
        return [
            $mutasi->tanggal->format('Y-m-d'),
            $mutasi->jenis->getLabel(),
            $mutasi->keterangan,
            $mutasi->debit(),
            $mutasi->kredit(),
            $mutasi->saldo,
            $mutasi->pemasukan(),
        ];
    }
}
