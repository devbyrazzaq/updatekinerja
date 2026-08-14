<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\UnitKerja;
use App\Services\PermissionRegistrar;

/**
 * Unit kerja yang menjadi dasar perhitungan widget, mengikuti penyaring halaman induk
 * lewat property `$unitKerjaId`. Tanpa unit terpilih, cakupannya seluruh unit kerja
 * aktif yang boleh diakses pengguna — sama dengan yang dibaca halamannya.
 */
trait ScopesUnitKerja
{
    /**
     * @return array<int, int>
     */
    protected function scopedUnitIds(): array
    {
        if ($this->unitKerjaId !== null) {
            return [$this->unitKerjaId];
        }

        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        $query = UnitKerja::query()->where('is_active', true);

        if (! $user->isPrivileged()) {
            $query->whereIn('id', PermissionRegistrar::permittedUnitIds($user)->all());
        }

        return $query->pluck('id')->all();
    }
}
