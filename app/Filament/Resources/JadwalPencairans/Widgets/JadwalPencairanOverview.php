<?php

namespace App\Filament\Resources\JadwalPencairans\Widgets;

use App\Enums\EnumStatusPencairan;
use App\Models\JadwalPencairan;
use App\Models\RealisasiProgramKerja;
use App\Services\KonteksProgramKerja;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

/**
 * Ringkasan jadwal pencairan pada tahun kerja aktif: banyaknya jadwal, nominal yang
 * anggarannya sudah diserahkan, dan jadwal yang berstatus selesai — yaitu jadwal yang
 * seluruh realisasinya sudah dicairkan.
 */
class JadwalPencairanOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected array|int|null $columns = 3;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $jumlahJadwal = $this->jadwalQuery()->count();
        $jadwalSelesai = $this->jadwalQuery()->where('status', EnumStatusPencairan::Dicairkan->value)->count();
        $belumSelesai = $jumlahJadwal - $jadwalSelesai;

        $totalDijadwalkan = $this->totalNominal();
        $totalDicairkan = $this->totalNominal(hanyaDicairkan: true);

        return [
            Stat::make('Jumlah Jadwal', (string) $jumlahJadwal)
                ->description($belumSelesai > 0
                    ? $belumSelesai.' jadwal belum dicairkan seluruhnya'
                    : 'Seluruh jadwal sudah dicairkan')
                ->descriptionIcon(Heroicon::OutlinedCalendarDateRange)
                ->color($belumSelesai > 0 ? 'warning' : 'success'),
            Stat::make('Nominal Dicairkan', $this->rupiah($totalDicairkan))
                ->description($this->bandingkan($totalDicairkan, $totalDijadwalkan, 'dari nominal yang dijadwalkan'))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color($totalDicairkan > 0 ? 'primary' : 'gray'),
            Stat::make('Jadwal Selesai', (string) $jadwalSelesai)
                ->description($this->bandingkan($jadwalSelesai, $jumlahJadwal, 'dari seluruh jadwal'))
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color($jadwalSelesai > 0 ? 'success' : 'gray'),
        ];
    }

    /**
     * Jadwal pencairan pada tahun kerja aktif.
     *
     * @return Builder<JadwalPencairan>
     */
    protected function jadwalQuery(): Builder
    {
        return KonteksProgramKerja::applyPelaksanaan(JadwalPencairan::query());
    }

    /**
     * Nominal realisasi yang dijadwalkan pada tahun kerja aktif. Bila
     * `$hanyaDicairkan` aktif, hanya realisasi yang anggarannya sudah diserahkan
     * yang dihitung.
     */
    protected function totalNominal(bool $hanyaDicairkan = false): float
    {
        $query = RealisasiProgramKerja::query()
            ->whereHas(
                'jadwalPencairan',
                fn (Builder $query): Builder => KonteksProgramKerja::applyPelaksanaan($query),
            );

        if ($hanyaDicairkan) {
            $query->whereNotNull('dicairkan_at');
        }

        return (float) $query
            ->selectRaw('coalesce(sum(coalesce(nominal_disetujui, nominal_diajukan, anggaran_digunakan)), 0) as total')
            ->value('total');
    }

    protected function rupiah(float $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }

    /**
     * Persentase nilai terhadap pembandingnya, mis. "42% dari seluruh jadwal".
     */
    protected function bandingkan(float $nilai, float $pembanding, string $keterangan): string
    {
        if ($pembanding <= 0) {
            return 'Belum ada pembanding';
        }

        return Number::percentage($nilai / $pembanding * 100, maxPrecision: 1, locale: 'id')." {$keterangan}";
    }
}
