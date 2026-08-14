<?php

namespace App\Exports;

use App\Enums\EnumStatusPemasukan;
use App\Enums\EnumSumberPemasukan;
use App\Models\Pemasukan;

class PemasukansExport extends Export
{
    public function filename(): string
    {
        return 'pemasukan-unit-'.now()->format('Y-m-d');
    }

    public function title(): string
    {
        return 'Data Pemasukan Unit';
    }

    public function subtitle(): ?string
    {
        return 'Catatan pemasukan unit kerja beserta sumber, tanggal pelaksanaan, dan status verifikasinya.';
    }

    public function headings(): array
    {
        return ['unit_kerja', 'sumber', 'program_kerja', 'rincian_kegiatan', 'jenis_waktu', 'tanggal_pelaksanaan', 'tanggal_selesai', 'nominal_pendapatan', 'status', 'keterangan'];
    }

    public function columnLabels(): array
    {
        return [
            'unit_kerja' => 'Unit Kerja',
            'sumber' => 'Sumber',
            'program_kerja' => 'Program Kerja',
            'rincian_kegiatan' => 'Rincian Kegiatan',
            'jenis_waktu' => 'Jenis Waktu',
            'tanggal_pelaksanaan' => 'Tanggal Pelaksanaan',
            'tanggal_selesai' => 'Tanggal Selesai',
            'nominal_pendapatan' => 'Nominal Pendapatan',
            'status' => 'Status',
            'keterangan' => 'Keterangan',
        ];
    }

    public function summary(): array
    {
        $query = Pemasukan::query();

        return [
            'Total Catatan' => number_format((clone $query)->count(), 0, ',', '.'),
            'Pemasukan Valid' => number_format((clone $query)->where('status', EnumStatusPemasukan::Valid)->count(), 0, ',', '.'),
            'Nominal Valid' => 'Rp '.number_format(
                (float) $query->where('status', EnumStatusPemasukan::Valid)->sum('nominal_pendapatan'),
                0, ',', '.',
            ),
        ];
    }

    public function rows(): iterable
    {
        return Pemasukan::query()
            ->with(['unitKerja', 'pengajuanProgramKerja.penawaranProgramKerja', 'realisasiProgramKerja'])
            ->latest('tanggal_pelaksanaan')
            ->lazy()
            ->map(fn (Pemasukan $pemasukan): array => [
                $pemasukan->unitKerja?->name,
                $pemasukan->sumber?->getLabel(),
                match ($pemasukan->sumber) {
                    EnumSumberPemasukan::Pengajuan => $pemasukan->pengajuanProgramKerja?->penawaranProgramKerja?->name,
                    EnumSumberPemasukan::Realisasi => $pemasukan->realisasiProgramKerja?->name,
                    default => null,
                },
                $pemasukan->rincian_kegiatan,
                $pemasukan->jenis_waktu?->getLabel(),
                $pemasukan->tanggal_pelaksanaan?->format('Y-m-d'),
                // Kegiatan sehari tidak punya tanggal selesai yang bermakna.
                ($pemasukan->jenis_waktu?->isRentang() ?? false) ? $pemasukan->tanggal_selesai?->format('Y-m-d') : null,
                $pemasukan->nominal_pendapatan,
                $pemasukan->status?->getLabel(),
                $pemasukan->keterangan !== null ? trim(strip_tags($pemasukan->keterangan)) : null,
            ]);
    }
}
