<?php

namespace App\Filament\Pages\Widgets;

use App\Filament\Pages\MonitoringProgramKerja;
use App\Filament\Widgets\Concerns\MembacaMonitoring;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Reactive;

/**
 * Peringkat persentase capaian program kerja: sepuluh program dengan capaian
 * tertinggi, digambar mendatar agar nama programnya terbaca utuh.
 *
 * Program yang belum punya realisasi tuntas tidak ikut tampil — capaiannya belum
 * pernah dilaporkan, dan menggambarnya sebagai nol akan menyesatkan.
 *
 * Unit kerja maupun tahun kerja mengikuti penyaring di halaman {@see MonitoringProgramKerja}.
 */
class CapaianProgramKerjaChart extends ChartWidget
{
    use MembacaMonitoring;

    /**
     * Banyak program yang digambar. Lebih dari ini batangnya terlalu tipis untuk
     * dibaca; rinciannya tetap tersedia pada tabel di bawah grafik.
     */
    protected const BATAS_PROGRAM = 10;

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
        return 'bar';
    }

    public function getHeading(): ?string
    {
        return 'Persentase Capaian Program Kerja';
    }

    public function getDescription(): ?string
    {
        return 'Sepuluh program kerja dengan ketercapaian target tertinggi menurut laporan realisasi yang sudah disetujui.';
    }

    /**
     * Digambar mendatar dengan sumbu nilai 0–100 agar tinggi batang selalu berarti
     * porsi target yang tercapai, bukan sekadar perbandingan antar program.
     */
    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        suggestedMax: 100,
                        ticks: { callback: (value) => value + '%' },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => context.parsed.x.toLocaleString('id-ID') + '%',
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
        $capaian = $this->capaian();

        if ($capaian->isEmpty()) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Capaian (%)',
                    'data' => $capaian->values()->all(),
                    'backgroundColor' => $capaian
                        ->values()
                        ->map(fn (float $nilai): string => match (true) {
                            $nilai >= 80 => 'rgba(16, 185, 129, 0.7)',
                            $nilai >= 50 => 'rgba(245, 158, 11, 0.7)',
                            default => 'rgba(239, 68, 68, 0.7)',
                        })
                        ->all(),
                    'borderWidth' => 0,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $capaian->keys()->all(),
        ];
    }

    /**
     * Capaian program kerja teratas, nama programnya dipendekkan agar sumbu grafik
     * tidak termakan label panjang.
     *
     * @return Collection<string, float>
     */
    protected function capaian(): Collection
    {
        return $this->monitoring()
            ->capaianPerProgram()
            ->take(self::BATAS_PROGRAM)
            ->mapWithKeys(fn (float $nilai, string $program): array => [Str::limit($program, 45) => $nilai]);
    }
}
