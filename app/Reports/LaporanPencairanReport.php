<?php

namespace App\Reports;

use App\Exports\JadwalPencairanExport;
use App\Models\JadwalPencairan;
use App\Models\RealisasiProgramKerja;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Laporan Pencairan satu gelombang jadwal: kop instansi, rincian realisasi
 * beserta rekening tujuannya, total yang dicairkan, lalu blok tanda tangan di
 * kaki halaman.
 *
 * Tanggal pada blok tanda tangan dipilih saat mengunduh (bukan tanggal cetak),
 * sedangkan jabatan, nomor karyawan, dan kotanya diambil dari Pengaturan Sistem.
 */
class LaporanPencairanReport extends Report
{
    /**
     * @param  JadwalPencairan  $jadwal  Jadwal yang dilaporkan.
     * @param  Carbon|null  $tanggal  Tanggal pada blok tanda tangan; null berarti hari ini.
     * @param  string|null  $namaPenandatangan  Nama penanda tangan; null berarti pengguna yang mengunduh.
     */
    public function __construct(
        protected JadwalPencairan $jadwal,
        protected ?Carbon $tanggal = null,
        protected ?string $namaPenandatangan = null,
    ) {}

    public function filename(): string
    {
        return 'laporan-pencairan-'.Str::slug($this->jadwal->name).'-'.$this->jadwal->tanggal_pencairan?->format('Y-m-d');
    }

    public function view(): string
    {
        return 'reports.pencairan';
    }

    public function footerNote(): string
    {
        return Setting::brandInstansi().' · Laporan Pencairan '.$this->jadwal->name;
    }

    public function data(): array
    {
        $export = new JadwalPencairanExport($this->jadwal);
        $tanggal = $this->tanggal ?? now();

        return [
            'title' => 'Laporan Pencairan Anggaran',
            'subtitle' => $this->jadwal->name.' — dicairkan '.$this->jadwal->tanggal_pencairan?->locale('id')->translatedFormat('d F Y'),
            'instansi' => Setting::brandInstansi(),
            'aplikasi' => Setting::brandNama(),
            'logo' => $this->logoPath(),
            'generatedAt' => now(),
            'jadwal' => $this->jadwal,
            'rincian' => $this->rincian($export),
            'total' => $this->jadwal->totalNominal(),
            'catatan' => $this->jadwal->catatan,
            'tanggal' => $tanggal,
            'kota' => Setting::penandatanganKota(),
            'penandatangan' => [
                'jabatan' => Setting::penandatanganJabatan(),
                'nama' => $this->namaPenandatangan(),
                'nomor' => Setting::penandatanganNomor(),
            ],
        ];
    }

    /**
     * Baris tabel laporan: satu realisasi per baris, lengkap dengan rekening
     * tujuannya karena laporan ini dipakai sebagai dasar pemindahbukuan.
     *
     * @return list<array{unit_kerja: string, kegiatan: string, metode: string, bank: string, nomor_rekening: string, atas_nama: string, nominal: float}>
     */
    protected function rincian(JadwalPencairanExport $export): array
    {
        return $export->realisasis()
            ->map(fn (RealisasiProgramKerja $realisasi): array => [
                'unit_kerja' => $realisasi->pengajuanProgramKerja?->unitKerja?->name ?? '-',
                'kegiatan' => $realisasi->name ?? '-',
                'metode' => $realisasi->metode_pembayaran?->getLabel() ?? 'Belum ditentukan',
                'bank' => $realisasi->rekeningBank?->bank?->name ?? '-',
                'nomor_rekening' => $realisasi->rekeningBank?->nomor_rekening ?? '-',
                'atas_nama' => $realisasi->rekeningBank?->atas_nama ?? '-',
                'nominal' => $realisasi->nominalPencairan(),
            ])
            ->all();
    }

    /**
     * Nama penanda tangan: nama yang dioper saat mengunduh, lalu nama pimpinan
     * beserta gelarnya dari Pengaturan Sistem, dan terakhir nama pengguna yang
     * sedang masuk selama pimpinannya belum diisi.
     */
    protected function namaPenandatangan(): string
    {
        $pimpinan = Setting::penandatanganNama();

        return $this->namaPenandatangan
            ?? ($pimpinan !== '' ? $pimpinan : null)
            ?? auth()->user()?->name
            ?? $this->jadwal->keuangan?->name
            ?? '';
    }
}
