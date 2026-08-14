<?php

namespace App\Exports;

use App\Models\TahunKerja;

class TahunKerjasExport extends Export
{
    public function filename(): string
    {
        return 'tahun-kerja-'.now()->format('Y-m-d');
    }

    public function title(): string
    {
        return 'Data Tahun Kerja';
    }

    public function subtitle(): ?string
    {
        return 'Daftar tahun kerja per periode jabatan beserta status keberjalanannya.';
    }

    public function headings(): array
    {
        return ['periode', 'name', 'tahun', 'description', 'start_datetime', 'end_datetime', 'status'];
    }

    public function columnLabels(): array
    {
        return [
            'periode' => 'Periode',
            'name' => 'Nama Tahun Kerja',
            'tahun' => 'Tahun',
            'description' => 'Deskripsi',
            'start_datetime' => 'Mulai',
            'end_datetime' => 'Selesai',
            'status' => 'Status',
        ];
    }

    public function summary(): array
    {
        return [
            'Total Tahun Kerja' => number_format(TahunKerja::query()->count(), 0, ',', '.'),
            'Periode Tercakup' => number_format(TahunKerja::query()->distinct()->count('periode_id'), 0, ',', '.'),
        ];
    }

    public function rows(): iterable
    {
        return TahunKerja::query()
            ->with('periode')
            ->orderBy('start_datetime')
            ->lazy()
            ->map(fn (TahunKerja $tahunKerja): array => [
                $tahunKerja->periode?->name,
                $tahunKerja->name,
                $tahunKerja->tahun,
                $tahunKerja->description !== null ? trim(strip_tags($tahunKerja->description)) : null,
                $tahunKerja->start_datetime?->format('Y-m-d H:i:s'),
                $tahunKerja->end_datetime?->format('Y-m-d H:i:s'),
                $tahunKerja->status?->value,
            ]);
    }
}
