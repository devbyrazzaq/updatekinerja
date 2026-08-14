<?php

namespace App\Exports;

use App\Models\PengajuanProgramKerja;

class PengajuanProgramKerjasExport extends Export
{
    public function filename(): string
    {
        return 'pengajuan-program-kerja-'.now()->format('Y-m-d');
    }

    public function title(): string
    {
        return 'Data Pengajuan Program Kerja';
    }

    public function subtitle(): ?string
    {
        return 'Pengajuan program kerja unit kerja beserta alokasi anggaran dan status verifikasinya.';
    }

    public function headings(): array
    {
        return ['program_kerja', 'unit_kerja', 'pengaju', 'alokasi_anggaran', 'status', 'deskripsi_kegiatan'];
    }

    public function columnLabels(): array
    {
        return [
            'program_kerja' => 'Program Kerja',
            'unit_kerja' => 'Unit Kerja',
            'pengaju' => 'Pengaju',
            'alokasi_anggaran' => 'Alokasi Anggaran',
            'status' => 'Status',
            'deskripsi_kegiatan' => 'Deskripsi Kegiatan',
        ];
    }

    public function summary(): array
    {
        $query = PengajuanProgramKerja::query();

        return [
            'Total Pengajuan' => number_format((clone $query)->count(), 0, ',', '.'),
            'Unit Kerja Tercakup' => number_format((clone $query)->distinct()->count('unit_kerja_id'), 0, ',', '.'),
            'Total Alokasi Anggaran' => 'Rp '.number_format((float) $query->sum('alokasi_anggaran'), 0, ',', '.'),
        ];
    }

    public function rows(): iterable
    {
        return PengajuanProgramKerja::query()
            ->with(['penawaranProgramKerja', 'unitKerja', 'user'])
            ->latest()
            ->lazy()
            ->map(fn (PengajuanProgramKerja $pengajuan): array => [
                $pengajuan->penawaranProgramKerja?->name,
                $pengajuan->unitKerja?->name,
                $pengajuan->user?->name,
                $pengajuan->alokasi_anggaran,
                $pengajuan->status?->getLabel(),
                $pengajuan->deskripsi_kegiatan !== null ? trim(strip_tags($pengajuan->deskripsi_kegiatan)) : null,
            ]);
    }
}
