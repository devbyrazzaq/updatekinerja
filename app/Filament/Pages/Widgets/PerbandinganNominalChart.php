<?php

namespace App\Filament\Pages\Widgets;

use App\Filament\Pages\PerbandinganMonitoring;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;

/**
 * Pagu, penyerapan, dan sisa pagu tiap pembanding berdampingan dalam rupiah.
 *
 * Angkanya tidak dihitung sendiri melainkan diterima dari halaman
 * {@see PerbandinganMonitoring}, karena halaman itulah yang tahu apa yang sedang
 * dibandingkan — unit kerja pada satu tahun, atau satu cakupan lintas tahun.
 */
class PerbandinganNominalChart extends ChartWidget
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
        return 'Perbandingan Anggaran';
    }

    public function getDescription(): ?string
    {
        return 'Pagu, anggaran yang terserap, dan sisanya pada tiap pembanding.';
    }

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
        if ($this->kolom === []) {
            return [];
        }

        return [
            'datasets' => [
                $this->dataset('Pagu Anggaran (Rp)', 'pagu', '148, 163, 184'),
                $this->dataset('Anggaran Terserap (Rp)', 'terserap', '59, 130, 246'),
                $this->dataset('Sisa Pagu (Rp)', 'sisa_pagu', '245, 158, 11'),
            ],
            'labels' => array_column($this->kolom, 'label'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function dataset(string $label, string $kunci, string $rgb): array
    {
        return [
            'label' => $label,
            'data' => array_map(fn (array $kolom): float => (float) ($kolom[$kunci] ?? 0), $this->kolom),
            'backgroundColor' => "rgba({$rgb}, 0.6)",
            'borderColor' => "rgb({$rgb})",
            'borderWidth' => 1,
            'borderRadius' => 4,
        ];
    }
}
