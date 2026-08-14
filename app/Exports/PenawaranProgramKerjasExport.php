<?php

namespace App\Exports;

use App\Enums\EnumFormatKolom;
use App\Models\PenawaranProgramKerja;

class PenawaranProgramKerjasExport extends Export
{
    public function filename(): string
    {
        return 'penawaran-program-kerja-'.now()->format('Y-m-d');
    }

    public function title(): string
    {
        return 'Data Penawaran Program Kerja';
    }

    public function subtitle(): ?string
    {
        return 'Penawaran program kerja per tahun kerja yang dapat diajukan unit kerja.';
    }

    public function headings(): array
    {
        return ['name', 'tahun_kerja', 'unit_kerja', 'bidang', 'kategori', 'program', 'kode_akun', 'target', 'nilai_standar', 'satuan_nilai_standar', 'is_active'];
    }

    public function columnLabels(): array
    {
        return [
            'name' => 'Nama Penawaran',
            'tahun_kerja' => 'Tahun Kerja',
            'unit_kerja' => 'Unit Kerja',
            'bidang' => 'Bidang',
            'kategori' => 'Kategori',
            'program' => 'Program Induk',
            'kode_akun' => 'Kode Akun',
            'target' => 'Target',
            'nilai_standar' => 'Nilai Standar',
            'satuan_nilai_standar' => 'Satuan',
            'is_active' => 'Status',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'target' => EnumFormatKolom::Angka,
        ];
    }

    public function summary(): array
    {
        $query = PenawaranProgramKerja::query();

        return [
            'Total Penawaran' => number_format((clone $query)->count(), 0, ',', '.'),
            'Aktif' => number_format((clone $query)->where('is_active', true)->count(), 0, ',', '.'),
            'Unit Kerja Tercakup' => number_format($query->distinct()->count('unit_kerja_id'), 0, ',', '.'),
        ];
    }

    public function rows(): iterable
    {
        return PenawaranProgramKerja::query()
            ->with(['tahunKerja', 'unitKerja', 'bidang', 'kategori', 'program', 'rekening'])
            ->orderBy('name')
            ->lazy()
            ->map(fn (PenawaranProgramKerja $penawaran): array => [
                $penawaran->name,
                $penawaran->tahunKerja?->name,
                $penawaran->unitKerja?->name,
                $penawaran->bidang?->name,
                $penawaran->kategori?->name,
                $penawaran->program?->name,
                $penawaran->rekening?->code,
                $penawaran->target,
                $penawaran->nilai_standar,
                $penawaran->satuan_nilai_standar,
                (int) $penawaran->is_active,
            ]);
    }
}
