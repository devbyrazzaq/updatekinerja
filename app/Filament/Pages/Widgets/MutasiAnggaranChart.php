<?php

namespace App\Filament\Pages\Widgets;

use App\Enums\EnumJenisMutasiAnggaran;
use App\Filament\Widgets\Concerns\ScopesUnitKerja;
use App\Models\TahunKerja;
use App\Services\BukuAnggaran;
use App\Services\MutasiAnggaran;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Reactive;

/**
 * Grafik batang mutasi Buku Anggaran per bulan sepanjang tahun kerja terpilih: kredit
 * (pengembalian sisa anggaran), debit (anggaran dicairkan & kekurangan yang dilunasi),
 * dan pemasukan yang tercatat terpisah.
 *
 * Baris pagu tidak ikut digambar karena ia saldo pembuka tahun kerja, bukan mutasi yang
 * terjadi pada bulan tertentu — memasukkannya hanya akan menenggelamkan batang lain.
 *
 * Pengelompokan bulan dilakukan di PHP, bukan lewat fungsi tanggal basis data, agar
 * hasilnya sama pada MySQL maupun SQLite.
 */
class MutasiAnggaranChart extends ChartWidget
{
    use ScopesUnitKerja;

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

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'bar';
    }

    public function getHeading(): ?string
    {
        return 'Mutasi Anggaran per Bulan';
    }

    public function getDescription(): ?string
    {
        $tahunKerja = $this->tahunKerja();

        return 'Anggaran yang dicairkan, dikembalikan, dan pemasukan sepanjang '
            .($tahunKerja?->name ?? 'tahun kerja terpilih')
            .'. Pagu tidak digambar karena menjadi saldo pembuka buku.';
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
        $perBulan = $this->mutasiPerBulan();

        if ($perBulan->isEmpty()) {
            return [];
        }

        return [
            'datasets' => [
                $this->dataset('Kredit (Rp)', $perBulan, 'kredit', '34, 197, 94'),
                $this->dataset('Debit (Rp)', $perBulan, 'debit', '239, 68, 68'),
                $this->dataset('Pemasukan (Rp)', $perBulan, 'pemasukan', '59, 130, 246'),
            ],
            'labels' => $perBulan->keys()->all(),
        ];
    }

    /**
     * Satu batang per bulan untuk sisi buku tertentu.
     *
     * @param  Collection<string, array{kredit: float, debit: float, pemasukan: float}>  $perBulan
     * @return array<string, mixed>
     */
    protected function dataset(string $label, Collection $perBulan, string $sisi, string $rgb): array
    {
        return [
            'label' => $label,
            'data' => $perBulan->map(fn (array $bulan): float => $bulan[$sisi])->values()->all(),
            'backgroundColor' => "rgba({$rgb}, 0.5)",
            'borderColor' => "rgb({$rgb})",
            'borderWidth' => 1,
            'borderRadius' => 4,
        ];
    }

    /**
     * Total kredit, debit, dan pemasukan tiap bulan. Sumbu bulannya membentang penuh
     * sepanjang rentang waktu tahun kerja terpilih, sehingga bulan tanpa mutasi tetap
     * tampil bernilai nol dan posisi tiap batang mencerminkan waktu sebenarnya.
     *
     * @return Collection<string, array{kredit: float, debit: float, pemasukan: float}>
     */
    protected function mutasiPerBulan(): Collection
    {
        $mutasi = $this->mutasi()->reject(
            fn (MutasiAnggaran $baris): bool => $baris->jenis === EnumJenisMutasiAnggaran::PaguDitetapkan,
        );

        if ($mutasi->isEmpty()) {
            return collect();
        }

        $terkumpul = $mutasi
            ->groupBy(fn (MutasiAnggaran $baris): string => $baris->tanggal->format('Y-m'))
            ->map(fn (Collection $kelompok): array => [
                'kredit' => (float) $kelompok->sum(fn (MutasiAnggaran $baris): float => $baris->kredit()),
                'debit' => (float) $kelompok->sum(fn (MutasiAnggaran $baris): float => $baris->debit()),
                'pemasukan' => (float) $kelompok->sum(fn (MutasiAnggaran $baris): float => $baris->pemasukan()),
            ]);

        [$mulai, $selesai] = $this->rentangBulan($terkumpul->keys());

        $hasil = collect();

        for ($bulan = $mulai; $bulan->lessThanOrEqualTo($selesai); $bulan = $bulan->copy()->addMonth()) {
            $hasil->put(
                $bulan->locale('id')->translatedFormat('M Y'),
                $terkumpul->get($bulan->format('Y-m'), ['kredit' => 0.0, 'debit' => 0.0, 'pemasukan' => 0.0]),
            );
        }

        return $hasil;
    }

    /**
     * Bulan awal dan akhir sumbu grafik: mengikuti rentang waktu tahun kerja terpilih.
     * Mutasi yang jatuh di luar rentang itu tetap dirangkul agar tidak ada data yang
     * hilang dari grafik.
     *
     * @param  Collection<int, string>  $bulanTerpakai  bulan mutasi dalam format `Y-m`
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function rentangBulan(Collection $bulanTerpakai): array
    {
        $mulai = Carbon::createFromFormat('Y-m', $bulanTerpakai->min())->startOfMonth();
        $selesai = Carbon::createFromFormat('Y-m', $bulanTerpakai->max())->startOfMonth();

        $tahunKerja = $this->tahunKerja();

        if ($tahunKerja?->start_datetime !== null) {
            $mulai = $mulai->min($tahunKerja->start_datetime->copy()->startOfMonth());
        }

        if ($tahunKerja?->end_datetime !== null) {
            $selesai = $selesai->max($tahunKerja->end_datetime->copy()->startOfMonth());
        }

        return [$mulai, $selesai];
    }

    /**
     * Baris buku unit kerja yang sedang dibaca. Tanpa unit kerja terpilih, buku seluruh
     * unit yang boleh diakses digabungkan.
     *
     * @return Collection<int, MutasiAnggaran>
     */
    protected function mutasi(): Collection
    {
        $tahunKerja = $this->tahunKerja();

        if ($tahunKerja === null) {
            return collect();
        }

        return collect($this->scopedUnitIds())
            ->flatMap(fn (int $unitKerjaId): Collection => BukuAnggaran::untukUnit($unitKerjaId, $tahunKerja)->mutasi());
    }

    protected function tahunKerja(): ?TahunKerja
    {
        return $this->tahunKerjaId !== null
            ? TahunKerja::find($this->tahunKerjaId)
            : null;
    }
}
