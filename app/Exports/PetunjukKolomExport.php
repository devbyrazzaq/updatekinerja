<?php

namespace App\Exports;

/**
 * Lembar kamus kolom untuk berkas template impor: satu baris per kolom, berisi nama
 * kolom, wajib/opsional, format nilai yang diterima, dan penjelasannya.
 *
 * Ditumpangkan pada berkas template lewat {@see Import::templateSheets()} supaya
 * aturan pengisian terbaca langsung di dalam berkasnya, bukan hanya di layar.
 */
class PetunjukKolomExport extends Export
{
    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: string}>  $baris
     *                                                                          Tiap baris: nama kolom, wajib/opsional, format nilai, penjelasan.
     */
    public function __construct(
        protected string $judul,
        protected ?string $keterangan,
        protected array $baris,
        protected string $filename = 'petunjuk-pengisian',
    ) {}

    public function filename(): string
    {
        return $this->filename;
    }

    public function title(): string
    {
        return $this->judul;
    }

    public function subtitle(): ?string
    {
        return $this->keterangan;
    }

    public function headings(): array
    {
        return ['kolom', 'pengisian', 'format', 'penjelasan'];
    }

    public function columnLabels(): array
    {
        return [
            'kolom' => 'Nama Kolom',
            'pengisian' => 'Pengisian',
            'format' => 'Format Nilai',
            'penjelasan' => 'Penjelasan',
        ];
    }

    public function rows(): iterable
    {
        return $this->baris;
    }
}
