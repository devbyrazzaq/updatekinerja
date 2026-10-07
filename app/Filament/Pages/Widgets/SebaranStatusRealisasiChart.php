<?php

namespace App\Filament\Pages\Widgets;

use App\Enums\EnumStatusRealisasi;
use App\Filament\Pages\MonitoringRealisasi;
use App\Filament\Widgets\Concerns\MembacaMonitoringRealisasi;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;

/**
 * Sebaran realisasi cakupan terpilih menurut statusnya, sehingga tahap yang menumpuk
 * langsung tampak: berapa yang masih diverifikasi, berapa yang menunggu pencairan
 * atau laporan, dan berapa yang sudah tuntas.
 *
 * Unit kerja maupun tahun kerja mengikuti penyaring di halaman {@see MonitoringRealisasi}.
 */
class SebaranStatusRealisasiChart extends ChartWidget
{
    use MembacaMonitoringRealisasi;

    /**
     * Warna irisan mengikuti warna status pada tabel dan badge, supaya status yang
     * sama dikenali dengan warna yang sama di seluruh aplikasi.
     *
     * @var array<string, string>
     */
    protected const WARNA = [
        'gray' => '156, 163, 175',
        'info' => '59, 130, 246',
        'warning' => '245, 158, 11',
        'success' => '16, 185, 129',
        'danger' => '239, 68, 68',
        'indigo' => '99, 102, 241',
        'violet' => '139, 92, 246',
        'cyan' => '6, 182, 212',
        'teal' => '20, 184, 166',
        'orange' => '249, 115, 22',
        'rose' => '244, 63, 94',
        'slate' => '100, 116, 139',
    ];

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

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    public function getHeading(): ?string
    {
        return 'Sebaran Status Realisasi';
    }

    public function getDescription(): ?string
    {
        return 'Posisi seluruh realisasi pada '.$this->cakupanTahunKerja().' menurut tahap yang sedang dijalaninya.';
    }

    /**
     * Legenda diletakkan di samping agar nama status yang panjang tidak terpotong,
     * dan tooltip menyertakan porsinya terhadap seluruh realisasi.
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
                                const porsi = total > 0 ? (context.parsed / total * 100).toFixed(1) : 0;

                                return context.label + ': ' + context.parsed + ' realisasi (' + porsi + '%)';
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
        $sebaran = $this->monitoringRealisasi()->sebaranStatus();

        if ($sebaran->isEmpty()) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Realisasi',
                    'data' => $sebaran->values()->all(),
                    'backgroundColor' => $sebaran
                        ->keys()
                        ->map(fn (string $label): string => 'rgba('.$this->warnaStatus($label).', 0.75)')
                        ->all(),
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $sebaran->keys()->all(),
        ];
    }

    /**
     * Warna rgb irisan sebuah status, dibaca dari warna badge statusnya.
     */
    protected function warnaStatus(string $label): string
    {
        $status = collect(EnumStatusRealisasi::cases())
            ->first(fn (EnumStatusRealisasi $status): bool => $status->getLabel() === $label);

        return static::WARNA[$status?->getColor()] ?? static::WARNA['gray'];
    }
}
