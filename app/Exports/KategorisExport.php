<?php

namespace App\Exports;

use App\Models\Kategori;

class KategorisExport extends Export
{
    public function filename(): string
    {
        return 'kategori-'.now()->format('Y-m-d');
    }

    public function title(): string
    {
        return 'Data Kategori';
    }

    public function subtitle(): ?string
    {
        return 'Daftar kategori yang mengelompokkan acuan dan penawaran program kerja.';
    }

    public function headings(): array
    {
        return ['code', 'name', 'description', 'is_active'];
    }

    public function columnLabels(): array
    {
        return [
            'code' => 'Kode',
            'name' => 'Nama Kategori',
            'description' => 'Deskripsi',
            'is_active' => 'Status',
        ];
    }

    public function summary(): array
    {
        $query = Kategori::query();

        return [
            'Total Kategori' => number_format((clone $query)->count(), 0, ',', '.'),
            'Aktif' => number_format((clone $query)->where('is_active', true)->count(), 0, ',', '.'),
            'Nonaktif' => number_format($query->where('is_active', false)->count(), 0, ',', '.'),
        ];
    }

    public function rows(): iterable
    {
        return Kategori::query()
            ->orderBy('name')
            ->lazy()
            ->map(fn (Kategori $kategori): array => [
                $kategori->code,
                $kategori->name,
                $kategori->description !== null ? trim(strip_tags($kategori->description)) : null,
                (int) $kategori->is_active,
            ]);
    }
}
