<?php

namespace App\Filament\Pages\Widgets;

use App\Filament\Pages\MonitoringProgramKerja;
use App\Filament\Widgets\Concerns\MembacaMonitoring;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;
use Livewire\Attributes\Reactive;

/**
 * Distribusi anggaran yang terserap. Tanpa unit kerja terpilih, anggaran dipecah
 * menurut unit kerjanya — terlihat unit mana yang paling banyak memakai pagu. Begitu
 * satu unit dipilih, pecahannya berpindah ke kategori program kerja, karena di dalam
 * satu unit yang menarik adalah jenis kegiatan yang menyerap anggarannya.
 *
 * Unit kerja maupun tahun kerja mengikuti penyaring di halaman {@see MonitoringProgramKerja}.
 */
class DistribusiAnggaranChart extends ChartWidget
{
    use MembacaMonitoring;

    /**
     * Warna irisan, berulang bila irisannya lebih banyak daripada warna yang tersedia.
     *
     * @var array<int, string>
     */
    protected const WARNA = [
        '59, 130, 246',
        '16, 185, 129',
        '245, 158, 11',
        '239, 68, 68',
        '139, 92, 246',
        '236, 72, 153',
        '14, 165, 233',
        '132, 204, 22',
    ];

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

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    public function getHeading(): ?string
    {
        return 'Distribusi Anggaran';
    }

    public function getDescription(): ?string
    {
        return $this->unitKerjaId === null
            ? 'Anggaran yang terserap, dipecah menurut unit kerja.'
            : 'Anggaran yang terserap, dipecah menurut kategori program kerja.';
    }

    /**
     * Tooltip menampilkan nominal beserta porsinya terhadap total yang terserap.
     */
    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    legend: { position: 'right' },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const total = context.dataset.data.reduce((jumlah, nilai) => jumlah + nilai, 0);
                                const porsi = total > 0 ? (context.parsed / total * 100).toFixed(1) : '0,0';

                                return context.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed) + ' (' + porsi + '%)';
                            },
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
        $distribusi = $this->distribusi();

        if ($distribusi->isEmpty()) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Anggaran Terserap (Rp)',
                    'data' => $distribusi->values()->all(),
                    'backgroundColor' => $distribusi
                        ->values()
                        ->map(fn (float $nominal, int $urutan): string => 'rgba('.self::WARNA[$urutan % count(self::WARNA)].', 0.75)')
                        ->all(),
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $distribusi->keys()->all(),
        ];
    }

    /**
     * @return Collection<string, float>
     */
    protected function distribusi(): Collection
    {
        return $this->unitKerjaId === null
            ? $this->monitoring()->distribusiPerUnitKerja()
            : $this->monitoring()->distribusiPerKategori();
    }
}
