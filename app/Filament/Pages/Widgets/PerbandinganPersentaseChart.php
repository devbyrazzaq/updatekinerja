<?php

namespace App\Filament\Pages\Widgets;

use App\Filament\Pages\PerbandinganMonitoring;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;

/**
 * Persentase penyerapan anggaran, pelaksanaan program kerja, dan capaian targetnya
 * tiap pembanding — ketiganya berbagi sumbu 0–100 sehingga perbedaannya langsung
 * terbaca tanpa terganggu besar-kecilnya pagu.
 *
 * Angkanya diterima dari halaman {@see PerbandinganMonitoring}.
 */
class PerbandinganPersentaseChart extends ChartWidget
{
    /**
     * Kolom pembanding dari halaman induk.
     *
     * @var array<int, array<string, mixed>>
     */
    #[Reactive]
    public array $kolom = [];

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    protected function getType(): string
    {
        return 'bar';
    }

    public function getHeading(): ?string
    {
        return 'Perbandingan Penyerapan & Capaian';
    }

    public function getDescription(): ?string
    {
        return 'Porsi pagu yang terserap, program kerja yang dilaksanakan, dan ketercapaian targetnya.';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: 100,
                        ticks: { callback: (value) => value + '%' },
                    },
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (context) => context.dataset.label + ': ' + context.parsed.y.toLocaleString('id-ID') + '%',
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
        if ($this->kolom === []) {
            return [];
        }

        return [
            'datasets' => [
                $this->dataset('Penyerapan Anggaran (%)', 'persentase_penyerapan', '59, 130, 246'),
                $this->dataset('Program Kerja Dilaksanakan (%)', 'persentase_pelaksanaan', '139, 92, 246'),
                $this->dataset('Capaian Target (%)', 'capaian', '16, 185, 129'),
            ],
            'labels' => array_column($this->kolom, 'label'),
        ];
    }

    /**
     * Persentase yang belum bisa dihitung digambar sebagai nol; tooltipnya tetap
     * jujur karena batangnya memang rata dengan sumbu.
     *
     * @return array<string, mixed>
     */
    protected function dataset(string $label, string $kunci, string $rgb): array
    {
        return [
            'label' => $label,
            'data' => array_map(
                fn (array $kolom): float => round((float) ($kolom[$kunci] ?? 0), 1),
                $this->kolom,
            ),
            'backgroundColor' => "rgba({$rgb}, 0.6)",
            'borderColor' => "rgb({$rgb})",
            'borderWidth' => 1,
            'borderRadius' => 4,
        ];
    }
}
