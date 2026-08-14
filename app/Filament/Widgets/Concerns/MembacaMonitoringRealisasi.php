<?php

namespace App\Filament\Widgets\Concerns;

use App\Services\MonitoringRealisasi;

/**
 * Sumber angka bersama widget monitoring realisasi: satu {@see MonitoringRealisasi}
 * yang dibentuk dari penyaring halaman induk (`$unitKerjaId` dan `$tahunKerjaId`)
 * lalu ditahan selama satu render.
 *
 * Menumpang {@see MembacaMonitoring} untuk pembacaan tahun kerja beserta pemformatan
 * rupiah dan persentase, sehingga kedua keluarga widget monitoring menampilkan angka
 * dengan gaya yang sama. Berbeda dengan monitoring anggaran, `$tahunKerjaId` boleh
 * dibiarkan null di sini — artinya seluruh tahun kerja ikut dipantau.
 */
trait MembacaMonitoringRealisasi
{
    use MembacaMonitoring;

    private ?MonitoringRealisasi $monitoringRealisasi = null;

    protected function monitoringRealisasi(): MonitoringRealisasi
    {
        return $this->monitoringRealisasi ??= MonitoringRealisasi::untukUnits(
            $this->scopedUnitIds(),
            $this->tahunKerjaId,
        );
    }

    /**
     * Sebutan cakupan waktu yang sedang dipantau, mis. "TA 2026" atau "seluruh tahun
     * kerja" ketika penyaring tahun kerja sengaja dikosongkan.
     */
    protected function cakupanTahunKerja(): string
    {
        return $this->tahunKerja()?->name ?? 'seluruh tahun kerja';
    }
}
