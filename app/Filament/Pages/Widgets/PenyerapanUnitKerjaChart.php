<?php

namespace App\Filament\Pages\Widgets;

use App\Filament\Pages\RingkasanUnitKerja;
use App\Filament\Widgets\Concerns\MembacaMonitoring;
use App\Services\RingkasanMonitoring;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Reactive;

/**
 * Pagu dan penyerapannya tiap unit kerja berdampingan, dengan garis persentase
 * penyerapan pada sumbu kanan — sehingga unit bertaraf anggaran kecil yang menyerap
 * hampir habis tetap terbaca setara dengan unit besar.
 *
 * Tahun kerjanya mengikuti penyaring di halaman {@see RingkasanUnitKerja}.
 */
class PenyerapanUnitKerjaChart extends ChartWidget
{
    use MembacaMonitoring;

    /**
     * Selalu seluruh unit kerja yang boleh diakses; halaman induk memang tidak
     * menyaring per unit.
     */
    public ?int $unitKerjaId = null;

    /**
     * Tahun kerja terpilih dari halaman induk. Null berarti tahun kerja belum ditetapkan.
     */
    #[Reactive]
    public ?int $tahunKerjaId = null;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    protected function getType(): string
    {
        return 'bar';
    }

    public function getHeading(): ?string
    {
        return 'Pagu & Penyerapan Anggaran per Unit Kerja';
    }

    public function getDescription(): ?string
    {
        return 'Perbandingan pagu dengan anggaran yang sudah terserap tiap unit kerja pada '
            .($this->tahunKerja()?->name ?? 'tahun kerja terpilih').'.';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        ticks: {
                            callback: (value) => 'Rp ' + new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(value),
                        },
                    },
                    persentase: {
                        beginAtZero: true,
                        position: 'right',
                        suggestedMax: 100,
                        grid: { drawOnChartArea: false },
                        ticks: { callback: (value) => value + '%' },
                    },
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (context) => context.dataset.yAxisID === 'persentase'
                                ? context.dataset.label + ': ' + context.parsed.y.toLocaleString('id-ID') + '%'
                                : context.dataset.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed.y),
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
        $unit = $this->unitKerjaBermakna();

        if ($unit->isEmpty()) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'type' => 'bar',
                    'label' => 'Pagu Anggaran (Rp)',
                    'data' => $unit->map(fn (RingkasanMonitoring $baris): float => $baris->pagu)->all(),
                    'backgroundColor' => 'rgba(148, 163, 184, 0.5)',
                    'borderColor' => 'rgb(148, 163, 184)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
                [
                    'type' => 'bar',
                    'label' => 'Anggaran Terserap (Rp)',
                    'data' => $unit->map(fn (RingkasanMonitoring $baris): float => $baris->terserap())->all(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.6)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
                [
                    'type' => 'line',
                    'label' => 'Penyerapan (%)',
                    'yAxisID' => 'persentase',
                    'data' => $unit->map(fn (RingkasanMonitoring $baris): float => round($baris->persentasePenyerapan() ?? 0, 1))->all(),
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgb(16, 185, 129)',
                    'borderWidth' => 2,
                    'tension' => 0.3,
                    'pointRadius' => 3,
                    'fill' => false,
                ],
            ],
            'labels' => $unit->map(fn (RingkasanMonitoring $baris): string => Str::limit($baris->label, 24))->all(),
        ];
    }

    /**
     * Unit kerja yang punya sesuatu untuk digambar: sudah berpagu atau sudah menyerap
     * anggaran. Unit yang belum tersentuh keduanya hanya akan menjadi batang kosong.
     *
     * @return Collection<int, RingkasanMonitoring>
     */
    protected function unitKerjaBermakna(): Collection
    {
        return $this->monitoring()
            ->perUnitKerja()
            ->filter(fn (RingkasanMonitoring $baris): bool => $baris->pagu > 0 || $baris->terserap() > 0)
            ->values();
    }
}
