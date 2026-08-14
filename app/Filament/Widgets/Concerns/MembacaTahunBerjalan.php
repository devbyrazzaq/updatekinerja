<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\TahunKerja;
use App\Services\KonteksProgramKerja;

/**
 * Sumber angka bersama widget dashboard. Berbeda dengan widget monitoring yang
 * mengikuti penyaring halaman, dashboard tidak punya form: cakupannya selalu tahun
 * kerja yang sedang berjalan dan seluruh unit kerja yang boleh diakses pengguna —
 * sama dengan yang dibaca menu harian.
 */
trait MembacaTahunBerjalan
{
    use MembacaMonitoring;

    /**
     * Dashboard tidak menyaring unit kerja. Property tetap ada karena dibaca
     * {@see ScopesUnitKerja} untuk menentukan cakupan unitnya.
     */
    public ?int $unitKerjaId = null;

    /**
     * Tahun kerja yang dipantau dashboard: selalu tahun berjalan. Null berarti belum
     * ada tahun kerja yang dijalankan, dan seluruh angka widget bernilai nol.
     */
    protected function tahunKerja(): ?TahunKerja
    {
        return $this->tahunKerja ??= KonteksProgramKerja::tahunBerjalan();
    }
}
