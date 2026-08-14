<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\TahunKerja;
use App\Services\MonitoringAnggaran;

/**
 * Sumber angka bersama widget monitoring: satu {@see MonitoringAnggaran} yang dibentuk
 * dari penyaring halaman induk (`$unitKerjaId` dan `$tahunKerjaId`) lalu ditahan
 * selama satu render, karena satu widget kerap membacanya lebih dari sekali.
 */
trait MembacaMonitoring
{
    use ScopesUnitKerja;

    private ?MonitoringAnggaran $monitoring = null;

    private ?TahunKerja $tahunKerja = null;

    protected function monitoring(): MonitoringAnggaran
    {
        return $this->monitoring ??= MonitoringAnggaran::untukUnits(
            $this->scopedUnitIds(),
            $this->tahunKerja(),
        );
    }

    protected function tahunKerja(): ?TahunKerja
    {
        if ($this->tahunKerjaId === null) {
            return null;
        }

        return $this->tahunKerja ??= TahunKerja::find($this->tahunKerjaId);
    }

    protected function rupiah(float $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }

    /**
     * Persentase bergaya Indonesia dengan satu angka di belakang koma.
     */
    protected function persen(?float $nilai, string $kosong = '-'): string
    {
        if ($nilai === null) {
            return $kosong;
        }

        return number_format($nilai, 1, ',', '.').'%';
    }
}
