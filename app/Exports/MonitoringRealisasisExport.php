<?php

namespace App\Exports;

use App\Enums\EnumFormatKolom;
use App\Enums\EnumJenisDokumenRealisasi;
use App\Models\RealisasiProgramKerja;
use App\Services\KodeDokumenRealisasi;
use App\Services\MonitoringRealisasi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Ekspor tabel halaman Monitoring Realisasi: realisasi program kerja yang sudah
 * diajukan pada cakupan unit kerja dan tahun kerja yang sedang dibaca.
 *
 * Barisnya diambil dari query yang sama dengan tabel halaman
 * ({@see MonitoringRealisasi::queryTabel()}), sehingga isi berkas selalu sejalan
 * dengan yang terlihat — termasuk pengecualian realisasi berstatus draf.
 *
 * Dua kolom terakhirnya berupa tautan "Lihat Proposal" dan "Lihat Laporan" yang
 * mengantar pembaca berkas kembali ke aplikasi untuk membuka dokumennya
 * ({@see self::tautanDokumen()}).
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
        return ['tahun_kerja', 'kegiatan', 'program_kerja', 'unit_kerja', 'nominal_disetujui', 'dicairkan_at', 'anggaran_digunakan', 'status_anggaran', 'persentase_ketercapaian', 'status', 'proposal', 'laporan'];
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
            'proposal' => 'Proposal',
            'laporan' => 'Laporan',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'dicairkan_at' => EnumFormatKolom::Tanggal,
            'proposal' => EnumFormatKolom::Tautan,
            'laporan' => EnumFormatKolom::Tautan,
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

    /**
     * Baris dikelompokkan per bulan pencairan supaya berkasnya terbaca sebagai
     * rekap bulanan tanpa perlu kolom bulan tersendiri. Realisasi yang belum cair
     * berkumpul di kelompok terakhir — {@see rows()} mengurutkannya ke sana.
     */
    public function groupLabel(array $row): ?string
    {
        $dicairkan = $this->columnValue($row, 'dicairkan_at');

        if (blank($dicairkan)) {
            return 'Belum Dicairkan';
        }

        return 'Dicairkan '.Carbon::parse((string) $dicairkan)
            ->locale(ExportTheme::LOCALE)
            ->translatedFormat('F Y');
    }

    public function rows(): iterable
    {
        return $this->monitoring()
            ->queryTabel()
            // Urutan menentukan pengelompokan: baris sebulan harus berdampingan,
            // dan yang belum cair (null) jatuh di paling bawah.
            ->orderByDesc('dicairkan_at')
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
                $this->tautanDokumen($realisasi, EnumJenisDokumenRealisasi::Proposal),
                $this->tautanDokumen($realisasi, EnumJenisDokumenRealisasi::Laporan),
            ]);
    }

    /**
     * Sel "Lihat Proposal"/"Lihat Laporan" yang bisa diklik langsung dari berkas
     * ekspor. Yang ditanam bukan alamat berkasnya — berkas realisasi privat dan hanya
     * terbuka lewat URL sementara — melainkan kode yang ditukar dengan URL baru pada
     * halaman pratinjau, setelah pembukanya terbukti sudah masuk.
     */
    protected function tautanDokumen(RealisasiProgramKerja $realisasi, EnumJenisDokumenRealisasi $jenis): ?Tautan
    {
        if (! $realisasi->punyaDokumen($jenis)) {
            return null;
        }

        return new Tautan(
            label: 'Lihat '.$jenis->getLabel(),
            url: KodeDokumenRealisasi::tautan($realisasi, $jenis),
        );
    }

    protected function monitoring(): MonitoringRealisasi
    {
        return MonitoringRealisasi::untukUnits($this->unitKerjaIds, $this->tahunKerjaId);
    }
}
