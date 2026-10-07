<?php

namespace App\Filament\Resources\Pemasukans\Widgets;

use App\Enums\EnumStatusPemasukan;
use App\Filament\Resources\Concerns\HasUnitKerjaStat;
use App\Models\Pemasukan;
use App\Models\Periode;
use App\Services\KonteksProgramKerja;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Grafik batang nominal pemasukan per tanggal pelaksanaan selama periode aktif.
 * Batangnya ditumpuk menjadi pemasukan valid dan yang masih dalam verifikasi, sama
 * seperti pemisahan pada {@see PemasukanOverview}, dan mengikuti unit kerja yang
 * dipilih di halaman.
 *
 * Hanya tanggal yang memiliki pemasukan yang ditampilkan: periode berlangsung
 * bertahun-tahun, sehingga sumbu harian penuh justru menenggelamkan batangnya.
 */
class PemasukanHarianChart extends ChartWidget
{
    use HasUnitKerjaStat;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Pemasukan per Tanggal';

    protected ?string $maxHeight = '280px';

    /**
     * Cache periode aktif agar tidak dikueri berulang dalam satu request.
     */
    protected Periode|false|null $cachedPeriode = null;

    public function getDescription(): ?string
    {
        $periode = $this->periodeAktif();

        if ($periode === null) {
            return 'Nominal pemasukan per tanggal pelaksanaan. Belum ada periode aktif, seluruh tanggal ditampilkan.';
        }

        $rentang = collect([$periode->start_datetime, $periode->end_datetime])
            ->map(fn ($tanggal): string => $tanggal?->locale('id')->translatedFormat('d M Y') ?? '…')
            ->implode(' – ');

        return "Nominal pemasukan per tanggal pelaksanaan selama {$periode->name} ({$rentang}).";
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Batang ditumpuk per status, sumbu dan tooltip diformat sebagai rupiah.
     */
    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                scales: {
                    x: { stacked: true },
                    y: {
                        stacked: true,
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
        $perTanggal = $this->pemasukanPerTanggal();

        if ($perTanggal->isEmpty()) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Valid',
                    'data' => $perTanggal->pluck('valid')->all(),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.6)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Dalam Verifikasi',
                    'data' => $perTanggal->pluck('verifikasi')->all(),
                    'backgroundColor' => 'rgba(245, 158, 11, 0.6)',
                    'borderColor' => 'rgb(245, 158, 11)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $perTanggal->keys()
                ->map(fn (string $tanggal): string => Carbon::parse($tanggal)->locale('id')->translatedFormat('d M Y'))
                ->all(),
        ];
    }

    /**
     * Total nominal per tanggal pelaksanaan, dipisah antara yang sudah valid dan yang
     * masih berjalan di alur verifikasi. Draf dan pemasukan ditolak tidak dihitung.
     * Pengelompokan dilakukan di PHP agar hasilnya sama pada MySQL maupun SQLite.
     *
     * @return Collection<string, array{valid: float, verifikasi: float}>
     */
    protected function pemasukanPerTanggal(): Collection
    {
        $statusBerjalan = EnumStatusPemasukan::berjalan();

        return $this->pemasukanQuery()
            ->whereIn('status', array_column([EnumStatusPemasukan::Valid, ...$statusBerjalan], 'value'))
            ->orderBy('tanggal_pelaksanaan')
            ->get(['tanggal_pelaksanaan', 'status', 'nominal_pendapatan'])
            ->groupBy(fn (Pemasukan $record): string => $record->tanggal_pelaksanaan->toDateString())
            ->map(fn (Collection $kelompok): array => [
                'valid' => (float) $kelompok
                    ->filter(fn (Pemasukan $record): bool => $record->status === EnumStatusPemasukan::Valid)
                    ->sum('nominal_pendapatan'),
                'verifikasi' => (float) $kelompok
                    ->filter(fn (Pemasukan $record): bool => in_array($record->status, $statusBerjalan, true))
                    ->sum('nominal_pendapatan'),
            ]);
    }

    /**
     * Pemasukan dalam rentang periode aktif, dibatasi unit kerja terpilih maupun scope
     * data pengguna.
     *
     * @return Builder<Pemasukan>
     */
    protected function pemasukanQuery(): Builder
    {
        $query = Pemasukan::query()->whereNotNull('tanggal_pelaksanaan');

        $periode = $this->periodeAktif();

        if ($periode?->start_datetime !== null) {
            $query->whereDate('tanggal_pelaksanaan', '>=', $periode->start_datetime);
        }

        if ($periode?->end_datetime !== null) {
            $query->whereDate('tanggal_pelaksanaan', '<=', $periode->end_datetime);
        }

        $unitIds = $this->scopedUnitIds();

        if ($unitIds !== null) {
            $query->whereIn('unit_kerja_id', $unitIds);
        }

        return $query;
    }

    /**
     * Periode yang ditandai aktif, atau periode tempat tahun kerja berjalan bernaung
     * bila belum ada yang ditandai.
     */
    protected function periodeAktif(): ?Periode
    {
        if ($this->cachedPeriode === null) {
            $this->cachedPeriode = Periode::query()->where('is_active', true)->first()
                ?? KonteksProgramKerja::tahunBerjalan()?->periode
                ?? false;
        }

        return $this->cachedPeriode ?: null;
    }
}
