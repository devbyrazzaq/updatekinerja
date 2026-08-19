<?php

namespace App\Filament\Pages\Concerns;

use App\Enums\EnumStatusRealisasi;
use App\Models\RealisasiProgramKerja;
use App\Services\PermissionRegistrar;

/**
 * Cara halaman-halaman turunan Monitoring Realisasi mengambil satu realisasi dari
 * kunci rutenya, dengan dua penjagaan yang sama: hanya realisasi yang benar-benar
 * berjalan (draf tidak pernah dipantau di sini) dan hanya unit kerja yang menjadi
 * cakupan data pengguna — aturan yang sama dengan tabel di halaman induknya.
 */
trait MembacaRealisasiTerpantau
{
    protected function resolveRecord(string $key): RealisasiProgramKerja
    {
        $realisasi = RealisasiProgramKerja::query()
            ->with(['pengajuanProgramKerja.unitKerja', 'pengajuanProgramKerja.penawaranProgramKerja.tahunKerja'])
            ->where('uuid', $key)
            ->where('status', '!=', EnumStatusRealisasi::Draft->value)
            ->firstOrFail();

        abort_unless($this->dapatDibaca($realisasi), 403);

        return $realisasi;
    }

    protected function dapatDibaca(RealisasiProgramKerja $realisasi): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->isPrivileged()) {
            return true;
        }

        $unitKerjaId = $realisasi->unitKerjaId();

        return $unitKerjaId !== null
            && PermissionRegistrar::permittedUnitIds($user)->contains($unitKerjaId);
    }
}
