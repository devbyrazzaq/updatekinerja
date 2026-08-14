<?php

namespace App\Filament\Pages\Widgets;

use App\Filament\Pages\MonitoringRealisasi;
use App\Filament\Widgets\Concerns\MembacaMonitoringRealisasi;
use App\Services\RingkasanRealisasi;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

/**
 * Ringkasan pemantauan realisasi cakupan terpilih: berapa yang masih berjalan, berapa
 * yang sudah tuntas, berapa anggaran yang sudah cair, dan berapa yang sudah
 * dipertanggungjawabkan lewat laporan.
 *
 * Unit kerja maupun tahun kerja mengikuti penyaring di halaman {@see MonitoringRealisasi}.
 */
class MonitoringRealisasiOverview extends StatsOverviewWidget
{
    use MembacaMonitoringRealisasi;

    /**
     * Unit kerja terpilih dari halaman induk. Null berarti semua unit yang boleh diakses.
     */
    #[Reactive]
    public ?int $unitKerjaId = null;

    /**
     * Tahun kerja terpilih dari halaman induk. Null berarti seluruh tahun kerja.
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
        $ringkasan = $this->monitoringRealisasi()->ringkasan();

        return [
            $this->statBerjalan($ringkasan),
            $this->statSelesai($ringkasan),
            $this->statDicairkan($ringkasan),
            $this->statDilaporkan($ringkasan),
        ];
    }

    protected function statBerjalan(RingkasanRealisasi $ringkasan): Stat
    {
        return Stat::make('Realisasi Berjalan', (string) $ringkasan->berjalan)
            ->description($ringkasan->jumlah > 0
                ? 'dari '.$ringkasan->jumlah.' realisasi pada '.$this->cakupanTahunKerja()
                : 'Belum ada realisasi yang diajukan pada '.$this->cakupanTahunKerja())
            ->descriptionIcon(Heroicon::OutlinedArrowPath)
            ->color($ringkasan->berjalan > 0 ? 'warning' : 'gray');
    }

    protected function statSelesai(RingkasanRealisasi $ringkasan): Stat
    {
        $persentase = $ringkasan->persentaseSelesai();

        return Stat::make('Realisasi Selesai', (string) $ringkasan->selesai)
            ->description($persentase === null
                ? 'Belum ada realisasi yang perlu dituntaskan'
                : $this->persen($persentase).' realisasi tuntas'
                    .($ringkasan->batal > 0 ? ' · '.$ringkasan->batal.' ditolak/dibatalkan' : ''))
            ->descriptionIcon(Heroicon::OutlinedCheckBadge)
            ->color(match (true) {
                $persentase === null => 'gray',
                $persentase >= 80 => 'success',
                $persentase >= 50 => 'info',
                default => 'warning',
            });
    }

    protected function statDicairkan(RingkasanRealisasi $ringkasan): Stat
    {
        return Stat::make('Anggaran Dicairkan', $this->rupiah($ringkasan->dicairkan))
            ->description($ringkasan->menungguCair() > 0
                ? $this->rupiah($ringkasan->menungguCair()).' sudah disetujui, menunggu cair'
                : $ringkasan->sudahCair.' dari '.($ringkasan->jumlah - $ringkasan->batal).' realisasi sudah menerima anggaran')
            ->descriptionIcon(Heroicon::OutlinedBanknotes)
            ->color($ringkasan->dicairkan > 0 ? 'primary' : 'gray');
    }

    protected function statDilaporkan(RingkasanRealisasi $ringkasan): Stat
    {
        $persentase = $ringkasan->persentaseDilaporkan();
        $tertunggak = $ringkasan->belumDipertanggungjawabkan();

        return Stat::make('Realisasi Akhir Dilaporkan', $this->rupiah($ringkasan->dilaporkan))
            ->description($persentase === null
                ? 'Belum ada anggaran yang dicairkan'
                : $this->persen($persentase).' dari anggaran yang dicairkan'
                    .($tertunggak > 0 ? ' · '.$tertunggak.' laporan belum tuntas' : ''))
            ->descriptionIcon(Heroicon::OutlinedClipboardDocumentList)
            ->color(match (true) {
                $persentase === null => 'gray',
                $tertunggak > 0 => 'warning',
                default => 'success',
            });
    }
}
