<?php

namespace App\Exports;

use App\Enums\EnumFormatKolom;
use App\Models\TahunKerja;
use App\Services\MonitoringAnggaran;
use Illuminate\Support\Str;

/**
 * Ekspor tabel halaman Monitoring Program Kerja: capaian tiap penawaran program
 * kerja pada cakupan unit kerja dan tahun kerja yang sedang dibaca.
 *
 * Angkanya diambil dari {@see MonitoringAnggaran} — sumber yang sama dengan tabel di
 * halaman — supaya berkas ekspor tidak pernah berbeda dari yang dilihat pengguna.
 */
class MonitoringProgramKerjasExport extends Export
{
    /**
     * @param  array<int, int>  $unitKerjaIds  Unit kerja yang tercakup pada tampilan.
     * @param  int|null  $tahunKerjaId  Tahun kerja yang dibaca; null berarti tanpa cakupan.
     * @param  string|null  $namaUnitKerja  Nama unit kerja tunggal bila tampilan disaring ke satu unit.
     */
    public function __construct(
        private readonly array $unitKerjaIds = [],
        private readonly ?int $tahunKerjaId = null,
        private readonly ?string $namaUnitKerja = null,
    ) {}

    public function filename(): string
    {
        return 'monitoring-program-kerja-'
            .Str::slug($this->namaUnitKerja ?? 'semua-unit')
            .'-'.now()->format('Y-m-d');
    }

    public function title(): string
    {
        return 'Monitoring Program Kerja';
    }

    public function subtitle(): ?string
    {
        return $this->cakupan();
    }

    public function headings(): array
    {
        return ['unit_kerja', 'program', 'bidang', 'kategori', 'program_induk', 'target', 'jumlah_pengajuan', 'alokasi', 'terserap', 'capaian'];
    }

    public function columnLabels(): array
    {
        return [
            'unit_kerja' => 'Unit Kerja',
            'program' => 'Program Kerja',
            'bidang' => 'Bidang',
            'kategori' => 'Kategori',
            'program_induk' => 'Program Induk',
            'target' => 'Target',
            'jumlah_pengajuan' => 'Pengajuan',
            'alokasi' => 'Anggaran Diajukan',
            'terserap' => 'Anggaran Terserap',
            'capaian' => 'Capaian',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'target' => EnumFormatKolom::Angka,
            'jumlah_pengajuan' => EnumFormatKolom::Angka,
            'capaian' => EnumFormatKolom::Persen,
        ];
    }

    public function summary(): array
    {
        $baris = $this->monitoring()->barisProgram();

        return [
            'Program Kerja' => number_format($baris->count(), 0, ',', '.'),
            'Anggaran Diajukan' => 'Rp '.number_format((float) $baris->sum('alokasi'), 0, ',', '.'),
            'Anggaran Terserap' => 'Rp '.number_format((float) $baris->sum('terserap'), 0, ',', '.'),
        ];
    }

    public function rows(): iterable
    {
        return $this->monitoring()
            ->barisProgram()
            ->values()
            ->map(fn (array $baris): array => [
                $baris['unit_kerja'],
                $baris['program'],
                $baris['bidang'],
                $baris['kategori'],
                $baris['program_induk'],
                $baris['target'],
                $baris['jumlah_pengajuan'],
                $baris['alokasi'],
                $baris['terserap'],
                $baris['capaian'],
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

    /**
     * Keterangan cakupan yang sedang dibaca, tampil sebagai anak judul dokumen.
     */
    protected function cakupan(): string
    {
        $tahunKerja = $this->tahunKerja()?->name;

        return ($this->namaUnitKerja ?? 'Seluruh Unit Kerja')
            .($tahunKerja !== null ? ' · '.$tahunKerja : '');
    }
}
