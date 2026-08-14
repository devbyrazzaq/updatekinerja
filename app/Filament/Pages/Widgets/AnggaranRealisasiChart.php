<?php

namespace App\Filament\Pages\Widgets;

use App\Filament\Pages\MonitoringRealisasi;
use App\Filament\Widgets\Concerns\MembacaMonitoringRealisasi;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;

/**
 * Grafik batang aliran anggaran realisasi: berapa yang benar-benar dicairkan, dan
 * berapa yang sudah dipertanggungjawabkan lewat laporan realisasi. Jarak antara kedua
 * batang itulah anggaran yang sudah keluar tetapi laporannya belum masuk.
 *
 * Sumbunya menyesuaikan cakupan: per bulan bila satu tahun kerja dipantau, per tahun
 * kerja bila penyaring tahun kerja dikosongkan — sehingga tahun-tahun sebelumnya bisa
 * disandingkan dengan tahun berjalan pada satu grafik.
 *
 * Unit kerja maupun tahun kerja mengikuti penyaring di halaman {@see MonitoringRealisasi}.
 */
class AnggaranRealisasiChart extends ChartWidget
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

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'bar';
    }

    public function getHeading(): ?string
    {
        return 'Anggaran Realisasi per '.str($this->monitoringRealisasi()->labelPeriode())->title()->toString();
    }

    public function getDescription(): ?string
    {
        return 'Anggaran yang dicairkan pada '.$this->cakupanTahunKerja()
            .', disandingkan dengan realisasi akhir yang sudah dilaporkan unit kerja.';
    }

    /**
     * Sumbu dan tooltip diformat sebagai rupiah agar nominal besar tetap terbaca.
     */
    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => 'Rp ' + new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(value),
                        },
                    },
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (context) => context.dataset.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed.y),
                        },
                    },
                },
            }
        JS);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $perPeriode = $this->monitoringRealisasi()->anggaranPerPeriode();

        if ($perPeriode->isEmpty()) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Anggaran Dicairkan (Rp)',
                    'data' => $perPeriode->map(fn (array $periode): float => $periode['dicairkan'])->values()->all(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Realisasi Akhir Dilaporkan (Rp)',
                    'data' => $perPeriode->map(fn (array $periode): float => $periode['dilaporkan'])->values()->all(),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.5)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $perPeriode->keys()->all(),
        ];
    }
}
