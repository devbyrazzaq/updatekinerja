<?php

namespace App\Filament\Pages\Widgets;

use App\Filament\Pages\MonitoringProgramKerja;
use App\Filament\Widgets\Concerns\MembacaMonitoring;
use App\Services\RingkasanMonitoring;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

/**
 * Ringkasan monitoring cakupan terpilih: pagu yang tersedia, seberapa besar yang sudah
 * terserap, sisanya, dan capaian program kerjanya.
 *
 * Unit kerja maupun tahun kerja mengikuti penyaring di halaman {@see MonitoringProgramKerja}.
 */
class MonitoringOverview extends StatsOverviewWidget
{
    use MembacaMonitoring;

    /**
     * Unit kerja terpilih dari halaman induk. Null berarti semua unit yang boleh diakses.
     */
    #[Reactive]
    public ?int $unitKerjaId = null;

    /**
     * Tahun kerja terpilih dari halaman induk. Null berarti tahun kerja belum ditetapkan.
     */
    #[Reactive]
    public ?int $tahunKerjaId = null;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected array|int|null $columns = 2;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $ringkasan = $this->monitoring()->ringkasan();

        return [
            $this->statPagu($ringkasan),
            $this->statPenyerapan($ringkasan),
            $this->statSisa($ringkasan),
            $this->statCapaian($ringkasan),
        ];
    }

    protected function statPagu(RingkasanMonitoring $ringkasan): Stat
    {
        $jumlahUnit = count($this->monitoring()->namaUnitKerja());

        return Stat::make('Pagu Anggaran', $this->rupiah($ringkasan->pagu))
            ->description(($this->tahunKerja()?->name ?? 'Tahun kerja belum dipilih').' · '.$jumlahUnit.' unit kerja')
            ->descriptionIcon(Heroicon::OutlinedBanknotes)
            ->color($ringkasan->pagu > 0 ? 'primary' : 'gray');
    }

    protected function statPenyerapan(RingkasanMonitoring $ringkasan): Stat
    {
        $persentase = $ringkasan->persentasePenyerapan();

        return Stat::make('Anggaran Terserap', $this->rupiah($ringkasan->terserap()))
            ->description($persentase === null
                ? 'Pagu anggaran belum ditetapkan'
                : $this->persen($persentase).' dari pagu anggaran')
            ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
            ->color(match (true) {
                $persentase === null => 'gray',
                $persentase > 100 => 'danger',
                $persentase >= 75 => 'success',
                default => 'info',
            });
    }

    protected function statSisa(RingkasanMonitoring $ringkasan): Stat
    {
        return Stat::make('Sisa Pagu Anggaran', $this->rupiah($ringkasan->sisaPagu()))
            ->description($ringkasan->komitmen > 0
                ? $this->rupiah($ringkasan->komitmen).' sudah diajukan, menunggu cair'
                : 'Belum ada anggaran yang menunggu pencairan')
            ->descriptionIcon(Heroicon::OutlinedWallet)
            ->color($ringkasan->sisaPagu() < 0 ? 'danger' : 'warning');
    }

    protected function statCapaian(RingkasanMonitoring $ringkasan): Stat
    {
        $capaian = $ringkasan->capaian;

        return Stat::make('Capaian Program Kerja', $this->persen($capaian, 'Belum ada'))
            ->description($ringkasan->jumlahProgram > 0
                ? $ringkasan->jumlahProgramDiajukan.' dari '.$ringkasan->jumlahProgram.' program kerja dilaksanakan'
                : 'Program kerja belum ditawarkan')
            ->descriptionIcon(Heroicon::OutlinedClipboardDocumentCheck)
            ->color(match (true) {
                $capaian === null => 'gray',
                $capaian >= 80 => 'success',
                $capaian >= 50 => 'warning',
                default => 'danger',
            });
    }
}
