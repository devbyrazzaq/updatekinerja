<?php

namespace App\Exports;

use App\Models\Bidang;

class BidangsExport extends Export
{
    public function filename(): string
    {
        return 'bidang-'.now()->format('Y-m-d');
    }

    public function title(): string
    {
        return 'Data Bidang';
    }

    public function subtitle(): ?string
    {
        return 'Daftar bidang sebagai pengelompokan utama acuan program kerja.';
    }

    public function headings(): array
    {
        return ['code', 'name', 'description', 'is_active'];
    }

    public function columnLabels(): array
    {
        return [
            'code' => 'Kode',
            'name' => 'Nama Bidang',
            'description' => 'Deskripsi',
            'is_active' => 'Status',
        ];
    }

    public function summary(): array
    {
        $query = Bidang::query();

        return [
            'Total Bidang' => number_format((clone $query)->count(), 0, ',', '.'),
            'Aktif' => number_format((clone $query)->where('is_active', true)->count(), 0, ',', '.'),
            'Nonaktif' => number_format($query->where('is_active', false)->count(), 0, ',', '.'),
        ];
    }

    public function rows(): iterable
    {
        return Bidang::query()
            ->orderBy('name')
            ->lazy()
            ->map(fn (Bidang $bidang): array => [
                $bidang->code,
                $bidang->name,
                $bidang->description !== null ? trim(strip_tags($bidang->description)) : null,
                (int) $bidang->is_active,
            ]);
    }
}
