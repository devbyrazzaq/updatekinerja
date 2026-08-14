<?php

namespace App\Exports;

use App\Models\AcuanProgramKerja;

class AcuanProgramKerjasExport extends Export
{
    public function filename(): string
    {
        return 'acuan-program-kerja-'.now()->format('Y-m-d');
    }

    public function title(): string
    {
        return 'Data Acuan Program Kerja';
    }

    public function subtitle(): ?string
    {
        return 'Acuan baku program kerja per unit kerja beserta indikator dan nilai standarnya.';
    }

    public function headings(): array
    {
        return ['name', 'unit_kerja', 'bidang', 'kategori', 'program', 'kode_akun', 'aktifitas', 'indikator', 'nilai_standar', 'satuan_nilai_standar', 'is_active'];
    }

    public function columnLabels(): array
    {
        return [
            'name' => 'Nama Acuan',
            'unit_kerja' => 'Unit Kerja',
            'bidang' => 'Bidang',
            'kategori' => 'Kategori',
            'program' => 'Program Induk',
            'kode_akun' => 'Kode Akun',
            'aktifitas' => 'Aktifitas',
            'indikator' => 'Indikator',
            'nilai_standar' => 'Nilai Standar',
            'satuan_nilai_standar' => 'Satuan',
            'is_active' => 'Status',
        ];
    }

    public function summary(): array
    {
        $query = AcuanProgramKerja::query();

        return [
            'Total Acuan' => number_format((clone $query)->count(), 0, ',', '.'),
            'Aktif' => number_format((clone $query)->where('is_active', true)->count(), 0, ',', '.'),
            'Unit Kerja Tercakup' => number_format($query->distinct()->count('unit_kerja_id'), 0, ',', '.'),
        ];
    }

    public function rows(): iterable
    {
        return AcuanProgramKerja::query()
            ->with(['unitKerja', 'bidang', 'kategori', 'program', 'rekening'])
            ->orderBy('name')
            ->lazy()
            ->map(fn (AcuanProgramKerja $acuan): array => [
                $acuan->name,
                $acuan->unitKerja?->name,
                $acuan->bidang?->name,
                $acuan->kategori?->name,
                $acuan->program?->name,
                $acuan->rekening?->code,
                $acuan->aktifitas !== null ? trim(strip_tags($acuan->aktifitas)) : null,
                $acuan->indikator !== null ? trim(strip_tags($acuan->indikator)) : null,
                $acuan->nilai_standar,
                $acuan->satuan_nilai_standar,
                (int) $acuan->is_active,
            ]);
    }
}
