<?php

namespace App\Exports;

use App\Filament\Pages\PerbandinganMonitoring;
use Illuminate\Support\Str;

/**
 * Ekspor matriks halaman Perbandingan Monitoring: satu baris per metrik, satu kolom
 * per pembanding (unit kerja atau tahun kerja, mengikuti mode yang dipilih).
 *
 * Berbeda dari ekspor lain yang kolomnya tetap, kolom di sini lahir dari pilihan
 * pengguna — karenanya {@see headings()} dirakit saat itu juga. Nilainya pun
 * diformat sebagai teks di kelas ini, bukan lewat tipe kolom, sebab satuan berbeda
 * per baris (rupiah, persen, angka) dan bukan per kolom.
 */
class PerbandinganMonitoringExport extends Export
{
    /**
     * @param  array<int, array<string, mixed>>  $kolom  Pembanding beserta angkanya, dari {@see PerbandinganMonitoring::kolom()}.
     * @param  array<int, array{label: string, kunci: string, format: string, keterangan: string}>  $metrik  Baris matriks, dari {@see PerbandinganMonitoring::metrik()}.
     * @param  string  $cakupan  Keterangan cakupan yang sedang dibandingkan.
     */
    public function __construct(
        private readonly array $kolom = [],
        private readonly array $metrik = [],
        private readonly string $cakupan = '',
    ) {}

    public function filename(): string
    {
        return 'perbandingan-monitoring-'.now()->format('Y-m-d');
    }

    public function title(): string
    {
        return 'Perbandingan Monitoring';
    }

    public function subtitle(): ?string
    {
        return $this->cakupan !== '' ? $this->cakupan : null;
    }

    public function headings(): array
    {
        return ['metrik', 'keterangan', ...$this->kunciKolom()];
    }

    public function columnLabels(): array
    {
        $labels = [
            'metrik' => 'Metrik',
            'keterangan' => 'Cara Membaca',
        ];

        foreach ($this->kunciKolom() as $index => $kunci) {
            $labels[$kunci] = (string) ($this->kolom[$index]['label'] ?? 'Pembanding '.($index + 1));
        }

        return $labels;
    }

    public function summary(): array
    {
        return [
            'Pembanding' => number_format(count($this->kolom), 0, ',', '.'),
            'Metrik Dibandingkan' => number_format(count($this->metrik), 0, ',', '.'),
        ];
    }

    public function rows(): iterable
    {
        foreach ($this->metrik as $metrik) {
            $baris = [$metrik['label'], $metrik['keterangan']];

            foreach ($this->kolom as $kolom) {
                $baris[] = $this->format($kolom[$metrik['kunci']] ?? null, $metrik['format']);
            }

            yield $baris;
        }
    }

    /**
     * Key mesin tiap kolom pembanding, dirakit dari labelnya agar tetap terbaca
     * ketika berkas dibuka, mis. "Fakultas Teknik" → `fakultas_teknik`.
     *
     * @return list<string>
     */
    protected function kunciKolom(): array
    {
        $kunci = [];

        foreach ($this->kolom as $index => $kolom) {
            $dasar = Str::of((string) ($kolom['label'] ?? ''))->snake()->toString();

            $kunci[] = $dasar !== '' ? $dasar : 'pembanding_'.($index + 1);
        }

        return $kunci;
    }

    protected function format(mixed $nilai, string $format): string
    {
        if ($nilai === null) {
            return '—';
        }

        return match ($format) {
            'rupiah' => 'Rp '.number_format((float) $nilai, 0, ',', '.'),
            'persen' => number_format((float) $nilai, 1, ',', '.').'%',
            default => number_format((float) $nilai, 0, ',', '.'),
        };
    }
}
