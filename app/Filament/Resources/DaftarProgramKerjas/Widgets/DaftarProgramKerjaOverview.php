<?php

namespace App\Filament\Resources\DaftarProgramKerjas\Widgets;

use App\Enums\EnumStatusTahunKerja;
use App\Filament\Resources\Concerns\HasUnitKerjaStat;
use App\Models\PaguAnggaran;
use App\Models\PenawaranProgramKerja;
use App\Services\KonteksProgramKerja;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ringkasan Daftar Program Kerja mengikuti unit kerja yang dipilih di halaman.
 * Bila belum ada unit yang dipilih, angka dihitung dari seluruh unit yang boleh
 * diakses pengguna.
 */
class DaftarProgramKerjaOverview extends StatsOverviewWidget
{
    use HasUnitKerjaStat;

    protected ?string $pollingInterval = null;

    protected array|int|null $columns = 3;

    /**
     * Slot tahun kerja yang diringkas. Turunannya di grup Perencanaan meringkas slot
     * Perencanaan sehingga angkanya sejalan dengan tabel di bawahnya.
     */
    protected function slotTahunKerja(): EnumStatusTahunKerja
    {
        return EnumStatusTahunKerja::Berjalan;
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $tahunKerja = KonteksProgramKerja::tahunSlot($this->slotTahunKerja());

        if ($tahunKerja === null) {
            return [
                Stat::make('Tahun Kerja', 'Belum diatur')
                    ->description('Tetapkan konteks di Pengaturan Program Kerja.')
                    ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                    ->color('warning'),
            ];
        }

        $unitIds = $this->scopedUnitIds();

        $totalPagu = $this->totalPaguAnggaran($unitIds);
        $totalProgramKerja = $this->totalProgramKerja($unitIds);

        return [
            $this->unitStat($unitIds),
            Stat::make('Total Pagu Anggaran', $this->rupiah($totalPagu))
                ->description($this->unitKerjaId !== null
                    ? 'Pagu anggaran unit terpilih'
                    : 'Akumulasi pagu seluruh unit terkait')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color($totalPagu > 0 ? 'primary' : 'warning'),
            Stat::make('Total Program Kerja', (string) $totalProgramKerja)
                ->description('Program kerja aktif yang ditawarkan')
                ->descriptionIcon(Heroicon::OutlinedRectangleStack)
                ->color('success'),
        ];
    }

    /**
     * @param  array<int, int>|null  $unitIds
     */
    protected function totalPaguAnggaran(?array $unitIds): float
    {
        $query = KonteksProgramKerja::applySlot(PaguAnggaran::query(), $this->slotTahunKerja());

        if ($unitIds !== null) {
            $query->whereIn('unit_kerja_id', $unitIds);
        }

        return (float) $query->sum('amount');
    }

    /**
     * @param  array<int, int>|null  $unitIds
     */
    protected function totalProgramKerja(?array $unitIds): int
    {
        $query = KonteksProgramKerja::applySlot(
            PenawaranProgramKerja::query()->where('is_active', true),
            $this->slotTahunKerja(),
        );

        if ($unitIds !== null) {
            $query->whereIn('unit_kerja_id', $unitIds);
        }

        return $query->count();
    }

    protected function rupiah(float $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }
}
