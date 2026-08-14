<?php

namespace App\Exports;

use App\Models\Program;

class ProgramsExport extends Export
{
    public function filename(): string
    {
        return 'program-'.now()->format('Y-m-d');
    }

    public function title(): string
    {
        return 'Data Program Induk';
    }

    public function subtitle(): ?string
    {
        return 'Daftar program induk yang menaungi seluruh acuan dan penawaran program kerja.';
    }

    public function headings(): array
    {
        return ['name', 'description', 'is_active'];
    }

    public function columnLabels(): array
    {
        return [
            'name' => 'Nama Program Induk',
            'description' => 'Deskripsi',
            'is_active' => 'Status',
        ];
    }

    public function summary(): array
    {
        $query = Program::query();

        return [
            'Total Program Induk' => number_format((clone $query)->count(), 0, ',', '.'),
            'Aktif' => number_format((clone $query)->where('is_active', true)->count(), 0, ',', '.'),
            'Nonaktif' => number_format($query->where('is_active', false)->count(), 0, ',', '.'),
        ];
    }

    public function rows(): iterable
    {
        return Program::query()
            ->orderBy('name')
            ->lazy()
            ->map(fn (Program $program): array => [
                $program->name,
                $program->description !== null ? trim(strip_tags($program->description)) : null,
                (int) $program->is_active,
            ]);
    }
}
