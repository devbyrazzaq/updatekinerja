<?php

namespace App\Exports;

use App\Enums\EnumFormatKolom;
use App\Models\RealisasiProgramKerja;
use App\Services\MonitoringRealisasi;
use Illuminate\Support\Str;

/**
 * Ekspor tabel halaman Monitoring Realisasi: realisasi program kerja yang sudah
 * diajukan pada cakupan unit kerja dan tahun kerja yang sedang dibaca.
 *
 * Barisnya diambil dari query yang sama dengan tabel halaman
 * ({@see MonitoringRealisasi::queryTabel()}), sehingga isi berkas selalu sejalan
 * dengan yang terlihat — termasuk pengecualian realisasi berstatus draf.
 */
class MonitoringRealisasisExport extends Export
{
    /**
     * @param  array<int, int>  $unitKerjaIds  Unit kerja yang tercakup pada tampilan.
     * @param  int|null  $tahunKerjaId  Tahun kerja yang dibaca; null berarti seluruh tahun.
     * @param  string|null  $namaUnitKerja  Nama unit kerja tunggal bila tampilan disaring ke satu unit.
     */
    public function __construct(
        private readonly array $unitKerjaIds = [],
        private readonly ?int $tahunKerjaId = null,
        private readonly ?string $namaUnitKerja = null,
    ) {}

    public function filename(): string
    {
        return 'monitoring-realisasi-'
            .Str::slug($this->namaUnitKerja ?? 'semua-unit')
            .'-'.now()->format('Y-m-d');
    }

    public function title(): string
    {
        return 'Monitoring Realisasi Program Kerja';
    }

    public function subtitle(): ?string
    {
        return $this->namaUnitKerja ?? 'Seluruh Unit Kerja';
    }

    public function headings(): array
    {
        return ['tahun_kerja', 'kegiatan', 'program_kerja', 'unit_kerja', 'nominal_disetujui', 'dicairkan_at', 'anggaran_digunakan', 'status_anggaran', 'persentase_ketercapaian', 'status'];
    }

    public function columnLabels(): array
    {
        return [
            'tahun_kerja' => 'Tahun Kerja',
            'kegiatan' => 'Kegiatan',
            'program_kerja' => 'Program Kerja',
            'unit_kerja' => 'Unit Kerja',
            'nominal_disetujui' => 'Anggaran Disetujui',
            'dicairkan_at' => 'Dicairkan',
            'anggaran_digunakan' => 'Realisasi Akhir',
            'status_anggaran' => 'Status Anggaran',
            'persentase_ketercapaian' => 'Ketercapaian',
            'status' => 'Status',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'dicairkan_at' => EnumFormatKolom::Tanggal,
        ];
    }

    public function summary(): array
    {
        $query = $this->monitoring()->queryTabel();

        return [
            'Jumlah Realisasi' => number_format((clone $query)->count(), 0, ',', '.'),
            'Anggaran Disetujui' => 'Rp '.number_format((float) (clone $query)->sum('nominal_disetujui'), 0, ',', '.'),
            'Realisasi Akhir' => 'Rp '.number_format((float) $query->sum('anggaran_digunakan'), 0, ',', '.'),
        ];
    }

    public function rows(): iterable
    {
        return $this->monitoring()
            ->queryTabel()
            ->latest('created_at')
            ->lazy()
            ->map(fn (RealisasiProgramKerja $realisasi): array => [
                $realisasi->pengajuanProgramKerja?->penawaranProgramKerja?->tahunKerja?->name,
                $realisasi->name,
                $realisasi->pengajuanProgramKerja?->penawaranProgramKerja?->name,
                $realisasi->pengajuanProgramKerja?->unitKerja?->name,
                $realisasi->nominal_disetujui,
                $realisasi->dicairkan_at?->format('Y-m-d'),
                $realisasi->anggaran_digunakan,
                // Status anggaran hanya bermakna setelah laporan masuk.
                $realisasi->sudahAdaLaporan() ? $realisasi->status_anggaran?->getLabel() : null,
                $realisasi->persentase_ketercapaian,
                $realisasi->labelStatus(),
            ]);
    }

    protected function monitoring(): MonitoringRealisasi
    {
        return MonitoringRealisasi::untukUnits($this->unitKerjaIds, $this->tahunKerjaId);
    }
}
