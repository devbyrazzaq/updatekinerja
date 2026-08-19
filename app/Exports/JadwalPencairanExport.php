<?php

namespace App\Exports;

use App\Models\JadwalPencairan;
use App\Models\RealisasiProgramKerja;
use App\Reports\LaporanPencairanReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Rincian satu gelombang pencairan: seluruh realisasi yang dijadwalkan padanya
 * beserta nominal, cara pembayaran, dan rekening tujuannya. Dipakai berkas .xlsx
 * pada halaman detail jadwal; bentuk PDF-nya dirakit {@see LaporanPencairanReport}
 * yang punya tata letak surat sendiri.
 */
class JadwalPencairanExport extends Export
{
    public function __construct(protected JadwalPencairan $jadwal) {}

    public function filename(): string
    {
        return 'pencairan-'.Str::slug($this->jadwal->name).'-'.$this->jadwal->tanggal_pencairan?->format('Y-m-d');
    }

    public function title(): string
    {
        return 'Rincian Pencairan '.$this->jadwal->name;
    }

    public function subtitle(): ?string
    {
        return 'Realisasi program kerja yang dicairkan pada '
            .$this->jadwal->tanggal_pencairan?->locale('id')->translatedFormat('d F Y')
            .' beserta cara pembayaran dan rekening tujuannya.';
    }

    public function headings(): array
    {
        return ['unit_kerja', 'kegiatan', 'nominal_pencairan', 'metode_pembayaran', 'bank', 'nomor_rekening', 'atas_nama', 'status', 'dicairkan_at'];
    }

    public function columnLabels(): array
    {
        return [
            'unit_kerja' => 'Unit Kerja',
            'kegiatan' => 'Kegiatan',
            'nominal_pencairan' => 'Nominal Pencairan',
            'metode_pembayaran' => 'Cara Pembayaran',
            'bank' => 'Bank',
            'nomor_rekening' => 'Nomor Rekening',
            'atas_nama' => 'Atas Nama',
            'status' => 'Status',
            'dicairkan_at' => 'Dicairkan',
        ];
    }

    public function summary(): array
    {
        return [
            'Jumlah Realisasi' => number_format($this->jadwal->jumlahRealisasi(), 0, ',', '.'),
            'Total Pencairan' => 'Rp '.number_format($this->jadwal->totalNominal(), 0, ',', '.'),
            'Tanggal Pencairan' => (string) $this->jadwal->tanggal_pencairan?->locale('id')->translatedFormat('d F Y'),
            'Status' => $this->jadwal->status?->getLabel() ?? '-',
        ];
    }

    public function rows(): iterable
    {
        return $this->realisasis()->map(fn (RealisasiProgramKerja $realisasi): array => [
            $realisasi->pengajuanProgramKerja?->unitKerja?->name,
            $realisasi->name,
            $realisasi->nominalPencairan(),
            $realisasi->metode_pembayaran?->getLabel(),
            $realisasi->rekeningBank?->bank?->name,
            // Nomor rekening dibiarkan sebagai teks agar angka depannya tidak hilang.
            $realisasi->rekeningBank?->nomor_rekening,
            $realisasi->rekeningBank?->atas_nama,
            $realisasi->status?->getLabel(),
            $realisasi->dicairkan_at?->format('Y-m-d H:i'),
        ])->all();
    }

    /**
     * Realisasi pada jadwal ini, urut unit kerja lalu nama kegiatan supaya berkas
     * .xlsx dan laporan PDF menampilkan urutan yang sama.
     *
     * @return Collection<int, RealisasiProgramKerja>
     */
    public function realisasis(): Collection
    {
        return $this->jadwal->realisasiProgramKerjas()
            ->with(['pengajuanProgramKerja.unitKerja', 'rekeningBank.bank'])
            ->get()
            ->sortBy(fn (RealisasiProgramKerja $realisasi): string => ($realisasi->pengajuanProgramKerja?->unitKerja?->name ?? '').'|'.$realisasi->name)
            ->values();
    }
}
