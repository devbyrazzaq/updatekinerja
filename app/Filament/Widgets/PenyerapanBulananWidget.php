<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasWidgetAuthorization;
use App\Filament\Widgets\Concerns\MembacaTahunBerjalan;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

/**
 * Laju penyerapan anggaran tahun kerja berjalan: batang untuk anggaran yang cair
 * tiap bulan, garis untuk akumulasinya, dan garis putus-putus setinggi pagu sebagai
 * batas yang tidak boleh dilewati.
 *
 * Cakupannya seluruh unit kerja yang boleh diakses pengguna. Untuk memilah per unit
 * atau membandingkan tahun kerja lain, gunakan menu Monitoring Program Kerja.
 */
class PenyerapanBulananWidget extends ChartWidget
{
    use HasWidgetAuthorization;
    use MembacaTahunBerjalan;

    protected static ?int $sort = 4;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'bar';
    }

    public function getHeading(): ?string
    {
        return 'Laju Penyerapan Anggaran';
    }

    public function getDescription(): ?string
    {
        return 'Anggaran yang dicairkan tiap bulan pada '
            .($this->tahunKerja()?->name ?? 'tahun kerja berjalan')
            .', beserta akumulasinya terhadap pagu.';
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
        $perBulan = $this->monitoring()->penyerapanPerBulan();

        if ($perBulan->isEmpty()) {
            return [];
        }

        $pagu = $this->monitoring()->ringkasan()->pagu;

        $datasets = [
            [
                'type' => 'bar',
                'label' => 'Dicairkan (Rp)',
                'data' => $perBulan->map(fn (array $bulan): float => $bulan['terserap'])->values()->all(),
                'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                'borderColor' => 'rgb(59, 130, 246)',
                'borderWidth' => 1,
                'borderRadius' => 4,
                'order' => 2,
            ],
            [
                'type' => 'line',
                'label' => 'Akumulasi Penyerapan (Rp)',
                'data' => $perBulan->map(fn (array $bulan): float => $bulan['kumulatif'])->values()->all(),
                'borderColor' => 'rgb(16, 185, 129)',
                'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                'borderWidth' => 2,
                'tension' => 0.3,
                'fill' => true,
                'pointRadius' => 3,
                'order' => 1,
            ],
        ];

        if ($pagu > 0) {
            $datasets[] = $this->datasetPagu($perBulan, $pagu);
        }

        return [
            'datasets' => $datasets,
            'labels' => $perBulan->keys()->all(),
        ];
    }

    /**
     * Garis datar setinggi pagu anggaran sebagai pembanding akumulasi penyerapan.
     *
     * @param  Collection<string, array{terserap: float, kumulatif: float}>  $perBulan
     * @return array<string, mixed>
     */
    protected function datasetPagu(Collection $perBulan, float $pagu): array
    {
        return [
            'type' => 'line',
            'label' => 'Pagu Anggaran (Rp)',
            'data' => array_fill(0, $perBulan->count(), $pagu),
            'borderColor' => 'rgb(239, 68, 68)',
            'borderDash' => [6, 6],
            'borderWidth' => 2,
            'pointRadius' => 0,
            'fill' => false,
            'order' => 0,
        ];
    }
}
