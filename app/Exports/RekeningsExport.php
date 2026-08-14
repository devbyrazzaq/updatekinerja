<?php

namespace App\Exports;

use App\Models\Rekening;

class RekeningsExport extends Export
{
    public function filename(): string
    {
        return 'rekening-'.now()->format('Y-m-d');
    }

    public function title(): string
    {
        return 'Data Kode Akun';
    }

    public function subtitle(): ?string
    {
        return 'Daftar kode akun (rekening) beserta unit kerja pemiliknya.';
    }

    public function headings(): array
    {
        return ['code', 'unit_kerja', 'name', 'description', 'is_active'];
    }

    public function columnLabels(): array
    {
        return [
            'code' => 'Kode Akun',
            'unit_kerja' => 'Unit Kerja',
            'name' => 'Nama Akun',
            'description' => 'Deskripsi',
            'is_active' => 'Status',
        ];
    }

    public function summary(): array
    {
        $query = Rekening::query();

        return [
            'Total Kode Akun' => number_format((clone $query)->count(), 0, ',', '.'),
            'Aktif' => number_format((clone $query)->where('is_active', true)->count(), 0, ',', '.'),
            'Nonaktif' => number_format($query->where('is_active', false)->count(), 0, ',', '.'),
        ];
    }

    public function rows(): iterable
    {
        return Rekening::query()
            ->with('unitKerja')
            ->orderBy('code')
            ->lazy()
            ->map(fn (Rekening $rekening): array => [
                $rekening->code,
                $rekening->unitKerja?->name,
                $rekening->name,
                $rekening->description !== null ? trim(strip_tags($rekening->description)) : null,
                (int) $rekening->is_active,
            ]);
    }
}
