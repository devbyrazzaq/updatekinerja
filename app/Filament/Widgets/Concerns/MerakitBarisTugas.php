<?php

namespace App\Filament\Widgets\Concerns;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Perakit baris tugas dashboard, dipakai bersama oleh widget "Menunggu Keputusan Anda"
 * dan "Perlu Ditindaklanjuti".
 *
 * Angka tiap baris dibaca dari kueri resource masing-masing, jadi pembatasan tahun kerja
 * maupun unit kerja persis sama dengan yang akan pengguna lihat saat menu itu dibuka.
 */
trait MerakitBarisTugas
{
    /**
     * Satu baris tugas. Jumlahnya baru dihitung setelah menu dipastikan boleh dibuka,
     * agar dashboard tidak menjalankan kueri untuk menu yang tidak akan tampil.
     *
     * @param  class-string  $resource
     * @param  callable(): int  $jumlah
     * @return array<string, mixed>|null
     */
    protected function baris(string $resource, string $label, string $keterangan, callable $jumlah, string $warna, string|BackedEnum|null $icon = null): ?array
    {
        if (! $resource::canAccess()) {
            return null;
        }

        return [
            'label' => $label,
            'keterangan' => $keterangan,
            'jumlah' => $jumlah(),
            'url' => $resource::getUrl(),
            'icon' => $icon ?? $resource::getNavigationIcon() ?? Heroicon::OutlinedInbox,
            'warna' => $warna,
        ];
    }

    /**
     * Membuang baris yang tidak boleh diakses maupun yang sudah bersih.
     *
     * @param  array<int, array<string, mixed>|null>  $baris
     * @return array<int, array<string, mixed>>
     */
    protected function bersihkan(array $baris): array
    {
        return array_values(array_filter(
            $baris,
            fn (?array $item): bool => $item !== null && $item['jumlah'] > 0,
        ));
    }

    protected function hitung(Builder $query, string $status): int
    {
        return $query->where('status', $status)->count();
    }
}
