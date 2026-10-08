<?php

namespace App\Filament\Resources\RealisasiProgramKerjas\Widgets;

use App\Models\RealisasiProgramKerja;
use App\Services\KonteksProgramKerja;
use App\Services\PermissionRegistrar;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;

/**
 * Grafik batang penggunaan anggaran realisasi program kerja, dikelompokkan menurut
 * bulan anggarannya dicairkan (`dicairkan_at`) sehingga terlihat kapan anggaran
 * benar-benar terserap. Mengikuti unit kerja yang dipilih di halaman dan dibatasi
 * tahun kerja aktif, sama seperti {@see RealisasiProgramKerjaOverview}.
 *
 * Pengelompokan bulan dilakukan di PHP, bukan lewat fungsi tanggal basis data, agar
 * hasilnya sama pada MySQL maupun SQLite.
 */
class PenggunaanAnggaranChart extends ChartWidget
{
    /**
     * Unit kerja terpilih dari halaman induk. Null berarti semua unit yang boleh diakses.
     */
    #[Reactive]
    public ?int $unitKerjaId = null;

    /**
     * Permission menu induk widget ini, diisi halaman induk lewat data widget. Menjadi
     * dasar apakah akses seluruh unit pengguna berlaku di menu tersebut (lihat
     * User::canViewAllUnitData()). Terkunci agar tidak bisa diganti dari peramban.
     */
    #[Locked]
    public ?string $permissionLingkup = null;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Penggunaan Anggaran';

    protected ?string $description = 'Anggaran yang digunakan realisasi, dikelompokkan menurut bulan anggaran dicairkan.';

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Sumbu dan tooltip diformat sebagai rupiah agar nominal besar tetap terbaca.
     */
    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => 'Rp ' + new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(value),
                        },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => 'Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed.y),
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
        $perBulan = $this->penggunaanPerBulan();

        if ($perBulan->isEmpty()) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Anggaran Digunakan (Rp)',
                    'data' => $perBulan->values()->all(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $perBulan->keys()->all(),
        ];
    }

    /**
     * Total anggaran digunakan per bulan pencairan. Sumbu bulannya membentang penuh
     * sepanjang rentang waktu tahun kerja aktif, sehingga bulan yang belum ada
     * pencairannya tetap tampil bernilai nol dan posisi tiap batang mencerminkan
     * waktu sebenarnya dalam periode tersebut.
     *
     * @return Collection<string, float>
     */
    protected function penggunaanPerBulan(): Collection
    {
        $realisasi = $this->realisasiQuery()
            ->whereNotNull('dicairkan_at')
            ->get(['dicairkan_at', 'anggaran_digunakan']);

        if ($realisasi->isEmpty()) {
            return collect();
        }

        $terpakai = $realisasi
            ->groupBy(fn (RealisasiProgramKerja $record): string => $record->dicairkan_at->format('Y-m'))
            ->map(fn (Collection $kelompok): float => (float) $kelompok->sum('anggaran_digunakan'));

        [$mulai, $selesai] = $this->rentangBulan($terpakai->keys());

        $hasil = collect();

        for ($bulan = $mulai; $bulan->lessThanOrEqualTo($selesai); $bulan = $bulan->copy()->addMonth()) {
            $hasil->put(
                $bulan->locale('id')->translatedFormat('M Y'),
                $terpakai->get($bulan->format('Y-m'), 0.0),
            );
        }

        return $hasil;
    }

    /**
     * Bulan awal dan akhir sumbu grafik: mengikuti rentang waktu tahun kerja aktif.
     * Pencairan yang jatuh di luar rentang itu tetap dirangkul agar tidak ada data
     * yang hilang dari grafik, dan tanpa tahun kerja aktif sumbu jatuh kembali ke
     * rentang data pencairannya sendiri.
     *
     * @param  Collection<int, string>  $bulanTerpakai  bulan pencairan dalam format `Y-m`
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function rentangBulan(Collection $bulanTerpakai): array
    {
        $mulai = Carbon::createFromFormat('Y-m', $bulanTerpakai->min())->startOfMonth();
        $selesai = Carbon::createFromFormat('Y-m', $bulanTerpakai->max())->startOfMonth();

        $tahunKerja = KonteksProgramKerja::tahunBerjalan();

        if ($tahunKerja?->start_datetime !== null) {
            $mulai = $mulai->min($tahunKerja->start_datetime->copy()->startOfMonth());
        }

        if ($tahunKerja?->end_datetime !== null) {
            $selesai = $selesai->max($tahunKerja->end_datetime->copy()->startOfMonth());
        }

        return [$mulai, $selesai];
    }

    /**
     * Realisasi pada tahun kerja aktif, dibatasi unit kerja terpilih maupun scope data
     * pengguna.
     *
     * @return Builder<RealisasiProgramKerja>
     */
    protected function realisasiQuery(): Builder
    {
        $query = KonteksProgramKerja::applyPelaksanaanVia(
            RealisasiProgramKerja::query(),
            'pengajuanProgramKerja.penawaranProgramKerja',
        );

        $unitIds = $this->scopedUnitIds();

        if ($unitIds !== null) {
            $query->whereHas('pengajuanProgramKerja', fn (Builder $q): Builder => $q->whereIn('unit_kerja_id', $unitIds));
        }

        return $query;
    }

    /**
     * Id unit kerja yang menjadi dasar perhitungan. Null berarti seluruh unit.
     *
     * @return array<int, int>|null
     */
    protected function scopedUnitIds(): ?array
    {
        if ($this->unitKerjaId !== null) {
            return [$this->unitKerjaId];
        }

        $user = auth()->user();

        if ($user === null || $user->canViewAllUnitData($this->permissionLingkup)) {
            return null;
        }

        return PermissionRegistrar::permittedUnitIds($user)->all();
    }
}
