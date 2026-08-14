<?php

namespace App\Exports;

use App\Models\RealisasiProgramKerja;

class RealisasiProgramKerjasExport extends Export
{
    public function filename(): string
    {
        return 'realisasi-program-kerja-'.now()->format('Y-m-d');
    }

    public function title(): string
    {
        return 'Data Realisasi Program Kerja';
    }

    public function subtitle(): ?string
    {
        return 'Realisasi program kerja beserta nominal pencairan, penyelesaian anggaran, dan ketercapaiannya.';
    }

    public function headings(): array
    {
        return ['name', 'unit_kerja', 'nominal_diajukan', 'nominal_disetujui', 'persentase_persetujuan', 'anggaran_digunakan', 'status', 'status_pencairan', 'jadwal_pencairan', 'tanggal_pencairan', 'metode_pembayaran', 'rekening_tujuan', 'status_anggaran', 'nominal_selisih_anggaran', 'status_penyelesaian_anggaran', 'persentase_ketercapaian', 'evaluasi_pengerjaan'];
    }

    public function columnLabels(): array
    {
        return [
            'name' => 'Nama Realisasi',
            'unit_kerja' => 'Unit Kerja',
            'nominal_diajukan' => 'Nominal Diajukan',
            'nominal_disetujui' => 'Nominal Disetujui',
            'persentase_persetujuan' => '% Persetujuan',
            'anggaran_digunakan' => 'Anggaran Digunakan',
            'status' => 'Status',
            'status_pencairan' => 'Status Pencairan',
            'jadwal_pencairan' => 'Jadwal Pencairan',
            'tanggal_pencairan' => 'Tanggal Pencairan',
            'metode_pembayaran' => 'Metode Pembayaran',
            'rekening_tujuan' => 'Rekening Tujuan',
            'status_anggaran' => 'Status Anggaran',
            'nominal_selisih_anggaran' => 'Selisih Anggaran',
            'status_penyelesaian_anggaran' => 'Penyelesaian Anggaran',
            'persentase_ketercapaian' => '% Ketercapaian',
            'evaluasi_pengerjaan' => 'Evaluasi Pengerjaan',
        ];
    }

    public function summary(): array
    {
        $query = RealisasiProgramKerja::query();

        return [
            'Total Realisasi' => number_format((clone $query)->count(), 0, ',', '.'),
            'Nominal Disetujui' => 'Rp '.number_format((float) (clone $query)->sum('nominal_disetujui'), 0, ',', '.'),
            'Anggaran Digunakan' => 'Rp '.number_format((float) $query->sum('anggaran_digunakan'), 0, ',', '.'),
        ];
    }

    public function rows(): iterable
    {
        return RealisasiProgramKerja::query()
            ->with(['pengajuanProgramKerja.unitKerja', 'jadwalPencairan', 'rekeningBank.bank'])
            ->latest()
            ->lazy()
            ->map(fn (RealisasiProgramKerja $realisasi): array => [
                $realisasi->name,
                $realisasi->pengajuanProgramKerja?->unitKerja?->name,
                $realisasi->nominalDiajukan(),
                $realisasi->nominal_disetujui,
                $realisasi->persentasePersetujuan(),
                $realisasi->anggaran_digunakan,
                $realisasi->status?->getLabel(),
                $realisasi->status_pencairan?->getLabel(),
                $realisasi->jadwalPencairan?->name,
                $realisasi->jadwalPencairan?->tanggal_pencairan?->format('Y-m-d'),
                $realisasi->metode_pembayaran?->getLabel(),
                $realisasi->rekeningBank?->label(),
                $realisasi->status_anggaran?->getLabel(),
                $realisasi->nominal_selisih_anggaran,
                $realisasi->status_penyelesaian_anggaran?->getLabel(),
                $realisasi->persentase_ketercapaian,
                $realisasi->evaluasi_pengerjaan,
            ]);
    }
}
