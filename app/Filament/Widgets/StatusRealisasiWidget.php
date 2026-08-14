<?php

namespace App\Filament\Widgets;

use App\Enums\EnumStatusRealisasi;
use App\Filament\Widgets\Concerns\HasWidgetAuthorization;
use App\Filament\Widgets\Concerns\MembacaTahunBerjalan;
use App\Models\RealisasiProgramKerja;
use App\Services\KonteksProgramKerja;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Sebaran realisasi program kerja tahun berjalan menurut statusnya: terlihat berapa
 * yang masih diverifikasi, berapa yang menunggu pencairan atau laporan, dan berapa
 * yang sudah tuntas — sehingga tahap yang menumpuk langsung tampak.
 *
 * Status tanpa satu pun realisasi tidak ikut digambar agar irisannya tetap terbaca.
 */
class StatusRealisasiWidget extends ChartWidget
{
    use HasWidgetAuthorization;
    use MembacaTahunBerjalan;

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
    ];

    protected static ?int $sort = 5;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    public function getHeading(): ?string
    {
        return 'Status Realisasi Program Kerja';
    }

    public function getDescription(): ?string
    {
        return 'Posisi seluruh realisasi pada '
            .($this->tahunKerja()?->name ?? 'tahun kerja berjalan')
            .' menurut tahap yang sedang dijalaninya.';
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
        $sebaran = $this->sebaranStatus();

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
     * Jumlah realisasi tiap status, urut mengikuti alur statusnya. Draf ikut dihitung
     * karena tetap memakai jatah realisasi berjalan unit kerja.
     *
     * @return Collection<string, int>
     */
    protected function sebaranStatus(): Collection
    {
        if ($this->tahunKerja() === null) {
            return collect();
        }

        $jumlah = $this->realisasiQuery()
            ->toBase()
            ->selectRaw('status, COUNT(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        return collect(EnumStatusRealisasi::cases())
            ->mapWithKeys(fn (EnumStatusRealisasi $status): array => [
                $status->getLabel() => (int) ($jumlah[$status->value] ?? 0),
            ])
            ->filter(fn (int $total): bool => $total > 0);
    }

    /**
     * Realisasi tahun kerja berjalan milik unit kerja yang boleh diakses pengguna.
     */
    protected function realisasiQuery(): Builder
    {
        return KonteksProgramKerja::applyPelaksanaanVia(
            RealisasiProgramKerja::query(),
            'pengajuanProgramKerja.penawaranProgramKerja',
        )->whereHas(
            'pengajuanProgramKerja',
            fn (Builder $query): Builder => $query->whereIn('unit_kerja_id', $this->scopedUnitIds()),
        );
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
