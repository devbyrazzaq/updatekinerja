<?php

namespace App\Filament\Resources\Pemasukans\Widgets;

use App\Enums\EnumStatusPemasukan;
use App\Filament\Resources\Concerns\HasUnitKerjaStat;
use App\Models\Pemasukan;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ringkasan pemasukan mengikuti unit kerja yang dipilih di halaman. Bila belum ada
 * unit yang dipilih, angka dihitung dari seluruh unit yang boleh diakses pengguna.
 */
class PemasukanOverview extends StatsOverviewWidget
{
    use HasUnitKerjaStat;

    protected ?string $pollingInterval = null;

    protected array|int|null $columns = 2;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $unitIds = $this->scopedUnitIds();

        $totalPemasukan = $this->totalPemasukan($unitIds);
        $jumlahTransaksi = $this->jumlahTransaksi($unitIds);
        $menungguVerifikasi = $this->totalMenungguVerifikasi($unitIds);

        return [
            $this->unitStat($unitIds),
            Stat::make('Total Pemasukan', $this->rupiah($totalPemasukan))
                ->description('Hanya pemasukan yang sudah valid'
                    .($this->unitKerjaId !== null ? ' pada unit terpilih' : ' pada seluruh unit terkait'))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color($totalPemasukan > 0 ? 'primary' : 'warning'),
            Stat::make('Menunggu Verifikasi', $this->rupiah($menungguVerifikasi))
                ->description('Belum sah, belum masuk buku anggaran')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('warning'),
            Stat::make('Jumlah Transaksi', (string) $jumlahTransaksi)
                ->description('Catatan pemasukan tercatat')
                ->descriptionIcon(Heroicon::OutlinedRectangleStack)
                ->color('success'),
        ];
    }

    /**
     * Akumulasi pemasukan yang sudah sah. Sengaja dibatasi status Valid agar angkanya
     * konsisten dengan Buku Anggaran, yang juga hanya menghitung pemasukan valid.
     *
     * @param  array<int, int>|null  $unitIds
     */
    protected function totalPemasukan(?array $unitIds): float
    {
        return (float) $this->query($unitIds)
            ->where('status', EnumStatusPemasukan::Valid)
            ->sum('nominal_pendapatan');
    }

    /**
     * Nominal pemasukan yang masih berjalan di alur verifikasi: sudah tercatat namun
     * belum sah, sehingga tetap terlihat tanpa ikut terhitung sebagai pemasukan.
     *
     * @param  array<int, int>|null  $unitIds
     */
    protected function totalMenungguVerifikasi(?array $unitIds): float
    {
        return (float) $this->query($unitIds)
            ->whereIn('status', array_column(EnumStatusPemasukan::berjalan(), 'value'))
            ->sum('nominal_pendapatan');
    }

    /**
     * Query pemasukan yang sudah dibatasi pada unit kerja yang menjadi dasar perhitungan.
     *
     * @param  array<int, int>|null  $unitIds
     * @return Builder<Pemasukan>
     */
    protected function query(?array $unitIds): Builder
    {
        $query = Pemasukan::query();

        if ($unitIds !== null) {
            $query->whereIn('unit_kerja_id', $unitIds);
        }

        return $query;
    }

    /**
     * @param  array<int, int>|null  $unitIds
     */
    protected function jumlahTransaksi(?array $unitIds): int
    {
        return $this->query($unitIds)->count();
    }

    protected function rupiah(float $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }
}
