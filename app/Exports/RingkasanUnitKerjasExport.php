<?php

namespace App\Exports;

use App\Enums\EnumFormatKolom;
use App\Models\TahunKerja;
use App\Services\MonitoringAnggaran;
use App\Services\RingkasanMonitoring;

/**
 * Ekspor tabel halaman Ringkasan Unit Kerja: rekap pagu, penyerapan, dan capaian
 * tiap unit kerja pada tahun kerja yang sedang dibaca.
 *
 * Angkanya dirakit {@see MonitoringAnggaran} — sumber yang sama dengan tabel di
 * halaman. Kolom deskriptif yang di halaman tampil sebagai keterangan kecil di bawah
 * sel (jumlah program dilaksanakan, komitmen menunggu cair, realisasi selesai)
 * dinaikkan menjadi kolom tersendiri, karena berkas ekspor tidak punya ruang itu.
 */
class RingkasanUnitKerjasExport extends Export
{
    /**
     * @param  array<int, int>  $unitKerjaIds  Unit kerja yang tercakup pada tampilan.
     * @param  int|null  $tahunKerjaId  Tahun kerja yang dibaca; null berarti tanpa cakupan.
     */
    public function __construct(
        private readonly array $unitKerjaIds = [],
        private readonly ?int $tahunKerjaId = null,
    ) {}

    public function filename(): string
    {
        return 'ringkasan-unit-kerja-'.now()->format('Y-m-d');
    }

    public function title(): string
    {
        return 'Ringkasan Unit Kerja';
    }

    public function subtitle(): ?string
    {
        $tahunKerja = $this->tahunKerja()?->name;

        return 'Rekap pagu, penyerapan, dan capaian tiap unit kerja'
            .($tahunKerja !== null ? ' pada '.$tahunKerja : '').'.';
    }

    public function headings(): array
    {
        return ['unit_kerja', 'pagu', 'terserap', 'komitmen', 'sisa_pagu', 'persentase_penyerapan', 'jumlah_program', 'jumlah_program_diajukan', 'jumlah_pengajuan', 'jumlah_realisasi', 'jumlah_selesai', 'capaian'];
    }

    public function columnLabels(): array
    {
        return [
            'unit_kerja' => 'Unit Kerja',
            'pagu' => 'Pagu Anggaran',
            'terserap' => 'Terserap',
            'komitmen' => 'Menunggu Cair',
            'sisa_pagu' => 'Sisa Pagu',
            'persentase_penyerapan' => 'Penyerapan',
            'jumlah_program' => 'Program Kerja',
            // Berdampingan dengan "Program Kerja", satu kata sudah cukup jelas —
            // dan tidak patah di tengah kata pada kolom sesempit ini.
            'jumlah_program_diajukan' => 'Dilaksanakan',
            'jumlah_pengajuan' => 'Pengajuan',
            'jumlah_realisasi' => 'Realisasi',
            'jumlah_selesai' => 'Selesai',
            'capaian' => 'Capaian',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'jumlah_program' => EnumFormatKolom::Angka,
            'jumlah_program_diajukan' => EnumFormatKolom::Angka,
            'jumlah_pengajuan' => EnumFormatKolom::Angka,
            'jumlah_realisasi' => EnumFormatKolom::Angka,
            'jumlah_selesai' => EnumFormatKolom::Angka,
            'persentase_penyerapan' => EnumFormatKolom::Persen,
            'capaian' => EnumFormatKolom::Persen,
        ];
    }

    public function summary(): array
    {
        $ringkasan = $this->monitoring()->ringkasan();

        return [
            'Total Pagu' => 'Rp '.number_format($ringkasan->pagu, 0, ',', '.'),
            'Terserap' => 'Rp '.number_format($ringkasan->terserap(), 0, ',', '.'),
            'Penyerapan' => $ringkasan->persentasePenyerapan() === null
                ? 'Belum berpagu'
                : number_format($ringkasan->persentasePenyerapan(), 1, ',', '.').'%',
        ];
    }

    public function rows(): iterable
    {
        return $this->monitoring()
            ->perUnitKerja()
            ->map(fn (RingkasanMonitoring $ringkasan): array => [
                $ringkasan->label,
                $ringkasan->pagu,
                $ringkasan->terserap(),
                $ringkasan->komitmen,
                $ringkasan->sisaPagu(),
                $ringkasan->persentasePenyerapan(),
                $ringkasan->jumlahProgram,
                $ringkasan->jumlahProgramDiajukan,
                $ringkasan->jumlahPengajuan,
                $ringkasan->jumlahRealisasi,
                $ringkasan->jumlahSelesai,
                $ringkasan->capaian,
            ]);
    }

    protected function monitoring(): MonitoringAnggaran
    {
        return MonitoringAnggaran::untukUnits($this->unitKerjaIds, $this->tahunKerja());
    }

    protected function tahunKerja(): ?TahunKerja
    {
        return $this->tahunKerjaId !== null ? TahunKerja::find($this->tahunKerjaId) : null;
    }
}
