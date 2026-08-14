<?php

namespace App\Exports;

use App\Models\PaguAnggaran;

class PaguAnggaransExport extends Export
{
    public function filename(): string
    {
        return 'pagu-anggaran-'.now()->format('Y-m-d');
    }

    public function title(): string
    {
        return 'Data Pagu Anggaran';
    }

    public function subtitle(): ?string
    {
        return 'Pagu anggaran tiap unit kerja per tahun kerja.';
    }

    public function headings(): array
    {
        return ['tahun_kerja', 'unit_kerja', 'amount', 'description'];
    }

    public function columnLabels(): array
    {
        return [
            'tahun_kerja' => 'Tahun Kerja',
            'unit_kerja' => 'Unit Kerja',
            'amount' => 'Nominal Pagu',
            'description' => 'Keterangan',
        ];
    }

    public function summary(): array
    {
        $query = PaguAnggaran::query();

        return [
            'Jumlah Pagu' => number_format((clone $query)->count(), 0, ',', '.'),
            'Unit Kerja Tercakup' => number_format((clone $query)->distinct()->count('unit_kerja_id'), 0, ',', '.'),
            'Total Nominal' => 'Rp '.number_format((float) $query->sum('amount'), 0, ',', '.'),
        ];
    }

    public function rows(): iterable
    {
        return PaguAnggaran::query()
            ->with(['tahunKerja', 'unitKerja'])
            ->lazy()
            ->map(fn (PaguAnggaran $pagu): array => [
                $pagu->tahunKerja?->name,
                $pagu->unitKerja?->name,
                $pagu->amount,
                $pagu->description !== null ? trim(strip_tags($pagu->description)) : null,
            ]);
    }
}
