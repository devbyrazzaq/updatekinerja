<?php

namespace App\Exports;

use App\Models\UnitKerja;

class UnitKerjasExport extends Export
{
    public function filename(): string
    {
        return 'unit-kerja-'.now()->format('Y-m-d');
    }

    public function title(): string
    {
        return 'Data Unit Kerja';
    }

    public function subtitle(): ?string
    {
        return 'Daftar unit kerja pemilik pagu anggaran dan program kerja.';
    }

    public function headings(): array
    {
        return ['name', 'description', 'is_active'];
    }

    public function columnLabels(): array
    {
        return [
            'name' => 'Nama Unit Kerja',
            'description' => 'Deskripsi',
            'is_active' => 'Status',
        ];
    }

    public function summary(): array
    {
        $query = UnitKerja::query();

        return [
            'Total Unit Kerja' => number_format((clone $query)->count(), 0, ',', '.'),
            'Aktif' => number_format((clone $query)->where('is_active', true)->count(), 0, ',', '.'),
            'Nonaktif' => number_format($query->where('is_active', false)->count(), 0, ',', '.'),
        ];
    }

    public function rows(): iterable
    {
        return UnitKerja::query()
            ->orderBy('name')
            ->lazy()
            ->map(fn (UnitKerja $unitKerja): array => [
                $unitKerja->name,
                $unitKerja->description !== null ? trim(strip_tags($unitKerja->description)) : null,
                (int) $unitKerja->is_active,
            ]);
    }
}
