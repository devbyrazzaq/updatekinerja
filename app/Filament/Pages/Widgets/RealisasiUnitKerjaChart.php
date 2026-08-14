<?php

namespace App\Filament\Pages\Widgets;

use App\Filament\Pages\MonitoringRealisasi;
use App\Filament\Widgets\Concerns\MembacaMonitoringRealisasi;
use App\Services\RingkasanRealisasi;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;
use Livewire\Attributes\Reactive;

/**
 * Grafik batang bertumpuk jumlah realisasi tiap unit kerja, dipisah menurut posisinya:
 * yang masih berjalan, yang sudah tuntas, dan yang kandas. Dari sini terlihat unit
 * mana yang pekerjaannya menumpuk dan unit mana yang sudah menyelesaikan kegiatannya.
 *
 * Unit yang belum punya realisasi apa pun tidak ikut digambar agar sumbunya tidak
 * dipenuhi batang kosong.
 *
 * Unit kerja maupun tahun kerja mengikuti penyaring di halaman {@see MonitoringRealisasi}.
 */
class RealisasiUnitKerjaChart extends ChartWidget
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

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'bar';
    }

    public function getHeading(): ?string
    {
        return 'Realisasi per Unit Kerja';
    }

    public function getDescription(): ?string
    {
        return 'Jumlah realisasi tiap unit kerja pada '.$this->cakupanTahunKerja().', dipisah menurut posisinya.';
    }

    /**
     * Batangnya ditumpuk agar tinggi total tiap unit langsung terbaca sebagai jumlah
     * seluruh realisasinya, dan sumbunya dibuat mendatar supaya nama unit kerja yang
     * panjang tetap terbaca utuh.
     */
    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                indexAxis: 'y',
                scales: {
                    x: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
                    y: { stacked: true },
                },
                plugins: {
                    legend: { position: 'bottom' },
                },
            }
        JS);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $perUnitKerja = $this->barisUnitKerja();

        if ($perUnitKerja->isEmpty()) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Berjalan',
                    'data' => $perUnitKerja->map(fn (RingkasanRealisasi $unit): int => $unit->berjalan)->all(),
                    'backgroundColor' => 'rgba(245, 158, 11, 0.75)',
                    'borderWidth' => 0,
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Selesai',
                    'data' => $perUnitKerja->map(fn (RingkasanRealisasi $unit): int => $unit->selesai)->all(),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.75)',
                    'borderWidth' => 0,
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Ditolak/Dibatalkan',
                    'data' => $perUnitKerja->map(fn (RingkasanRealisasi $unit): int => $unit->batal)->all(),
                    'backgroundColor' => 'rgba(239, 68, 68, 0.75)',
                    'borderWidth' => 0,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $perUnitKerja->map(fn (RingkasanRealisasi $unit): string => $unit->label)->all(),
        ];
    }

    /**
     * Unit kerja yang punya realisasi, yang paling banyak lebih dahulu.
     *
     * @return Collection<int, RingkasanRealisasi>
     */
    protected function barisUnitKerja(): Collection
    {
        return $this->monitoringRealisasi()
            ->perUnitKerja()
            ->filter(fn (RingkasanRealisasi $unit): bool => $unit->jumlah > 0)
            ->sortByDesc(fn (RingkasanRealisasi $unit): int => $unit->jumlah)
            ->values();
    }
}
