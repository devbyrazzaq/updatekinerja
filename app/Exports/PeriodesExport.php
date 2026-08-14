<?php

namespace App\Exports;

use App\Models\Periode;

class PeriodesExport extends Export
{
    public function filename(): string
    {
        return 'periode-'.now()->format('Y-m-d');
    }

    public function title(): string
    {
        return 'Data Periode';
    }

    public function subtitle(): ?string
    {
        return 'Daftar periode jabatan beserta rentang waktu berlakunya.';
    }

    public function headings(): array
    {
        return ['name', 'description', 'start_datetime', 'end_datetime', 'is_active'];
    }

    public function columnLabels(): array
    {
        return [
            'name' => 'Nama Periode',
            'description' => 'Deskripsi',
            'start_datetime' => 'Mulai',
            'end_datetime' => 'Selesai',
            'is_active' => 'Status',
        ];
    }

    public function summary(): array
    {
        $query = Periode::query();

        return [
            'Total Periode' => number_format((clone $query)->count(), 0, ',', '.'),
            'Aktif' => number_format((clone $query)->where('is_active', true)->count(), 0, ',', '.'),
            'Nonaktif' => number_format($query->where('is_active', false)->count(), 0, ',', '.'),
        ];
    }

    public function rows(): iterable
    {
        return Periode::query()
            ->orderBy('start_datetime')
            ->lazy()
            ->map(fn (Periode $periode): array => [
                $periode->name,
                $periode->description !== null ? trim(strip_tags($periode->description)) : null,
                $periode->start_datetime?->format('Y-m-d H:i:s'),
                $periode->end_datetime?->format('Y-m-d H:i:s'),
                (int) $periode->is_active,
            ]);
    }
}
