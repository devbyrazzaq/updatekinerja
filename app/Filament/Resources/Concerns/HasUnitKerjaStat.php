<?php

namespace App\Filament\Resources\Concerns;

use App\Models\UnitKerja;
use App\Services\PermissionRegistrar;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;

/**
 * Stat "Unit Kerja" bersama seluruh ringkasan yang mengikuti penyaring unit kerja di
 * halaman induk (lihat trait HasUnitKerjaPageFilter). Cakupan perhitungan disebut apa
 * adanya: unit terpilih, satu-satunya unit yang boleh diakses, atau gabungan unit
 * beserta daftar unit yang tercakup.
 */
trait HasUnitKerjaStat
{
    /**
     * Unit kerja terpilih dari halaman induk. Null berarti semua unit yang boleh diakses.
     * Reaktif agar ringkasan ikut berubah saat pilihan unit di halaman diperbarui.
     */
    #[Reactive]
    public ?int $unitKerjaId = null;

    /**
     * Permission menu induk widget ini, diisi halaman induk lewat data widget. Menjadi
     * dasar apakah akses seluruh unit pengguna berlaku di menu tersebut (lihat
     * User::canViewAllUnitData()). Terkunci agar tidak bisa diganti dari peramban.
     */
    #[Locked]
    public ?string $permissionLingkup = null;

    /**
     * Stat unit kerja: nama unit terpilih, nama unit tunggal bila cakupan pengguna
     * memang hanya satu, atau "Semua Unit" beserta daftar unit yang tercakup.
     *
     * @param  array<int, int>|null  $unitIds
     */
    protected function unitStat(?array $unitIds): Stat
    {
        if ($this->unitKerjaId !== null) {
            $nama = UnitKerja::query()->whereKey($this->unitKerjaId)->value('name') ?? 'Unit tidak ditemukan';

            return Stat::make('Unit Kerja', $nama)
                ->description('Unit kerja yang sedang ditampilkan')
                ->descriptionIcon(Heroicon::OutlinedBuildingOffice2)
                ->color('gray');
        }

        $namaUnit = $this->namaUnitTercakup($unitIds);

        if (count($namaUnit) === 1) {
            return Stat::make('Unit Kerja', reset($namaUnit))
                ->description('Satu-satunya unit kerja dalam cakupan Anda')
                ->descriptionIcon(Heroicon::OutlinedBuildingOffice2)
                ->color('gray');
        }

        return Stat::make('Unit Kerja', 'Semua Unit')
            ->description($this->ringkasanDaftarUnit($namaUnit))
            ->descriptionIcon(Heroicon::OutlinedBuildingOffice2)
            ->color('gray')
            ->extraAttributes(['title' => implode(', ', $namaUnit)]);
    }

    /**
     * Id unit kerja yang menjadi dasar perhitungan. Null berarti tanpa batasan (seluruh unit).
     *
     * @return array<int, int>|null
     */
    protected function scopedUnitIds(): ?array
    {
        if ($this->unitKerjaId !== null) {
            return [$this->unitKerjaId];
        }

        $user = auth()->user();

        if ($user === null || $user->canViewAllUnitData($this->permissionLingkup)) {
            return null;
        }

        return PermissionRegistrar::permittedUnitIds($user)->all();
    }

    /**
     * Nama unit kerja yang menjadi cakupan perhitungan, terurut sesuai daftar di menu.
     *
     * @param  array<int, int>|null  $unitIds
     * @return array<int, string>
     */
    protected function namaUnitTercakup(?array $unitIds): array
    {
        $query = UnitKerja::query()->where('is_active', true)->orderBy('name');

        if ($unitIds !== null) {
            $query->whereKey($unitIds);
        }

        return $query->pluck('name')->all();
    }

    /**
     * Daftar unit yang tercakup, dipadatkan memakai singkatan dalam tanda kurung bila
     * ada. Sisanya diringkas menjadi "+N unit lainnya"; daftar utuh tersedia di tooltip.
     *
     * @param  array<int, string>  $namaUnit
     */
    protected function ringkasanDaftarUnit(array $namaUnit): string
    {
        $jumlah = count($namaUnit);

        if ($jumlah === 0) {
            return 'Belum ada unit kerja yang tercakup';
        }

        $ditampilkan = array_map($this->singkatanUnit(...), array_slice($namaUnit, 0, 5));
        $sisa = $jumlah - count($ditampilkan);

        return $jumlah.' unit: '.implode(', ', $ditampilkan)
            .($sisa > 0 ? ' +'.$sisa.' unit lainnya' : '');
    }

    /**
     * Singkatan unit kerja dari teks dalam tanda kurung, mis. "Fakultas Ekonomi dan
     * Bisnis ( FEB )" menjadi "FEB". Tanpa tanda kurung, nama dipakai apa adanya.
     */
    protected function singkatanUnit(string $nama): string
    {
        return preg_match('/\(([^()]+)\)[^()]*$/', $nama, $cocok) === 1
            ? trim($cocok[1])
            : $nama;
    }
}
