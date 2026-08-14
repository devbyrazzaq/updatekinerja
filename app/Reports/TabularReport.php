<?php

namespace App\Reports;

use App\Enums\EnumFormatKolom;
use App\Exports\Export;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Laporan PDF yang dirakit dari sebuah {@see Export}. Ekspor sudah menyimpan
 * seluruh yang dibutuhkan sebuah laporan — judul, label kolom, tipe tampilan tiap
 * kolom, angka ringkas, dan barisan datanya — sehingga setiap resource yang punya
 * tombol Ekspor otomatis bisa punya tombol Laporan PDF tanpa kelas laporan sendiri.
 *
 * Laporan dengan kebutuhan tata letak khusus tetap boleh menurunkan {@see Report}
 * langsung dan memakai view Blade-nya sendiri.
 */
class TabularReport extends Report
{
    /**
     * Batas jumlah kolom sebelum halaman diputar melintang. Di bawah ini tabel
     * masih lapang pada A4 tegak.
     */
    protected const LANDSCAPE_COLUMN_THRESHOLD = 6;

    public function __construct(protected Export $export) {}

    /**
     * @param  class-string<Export>  $export
     */
    public static function untuk(string $export): self
    {
        return new self(app($export));
    }

    public function filename(): string
    {
        return 'laporan-'.$this->export->filename();
    }

    public function view(): string
    {
        return 'reports.tabular';
    }

    public function landscape(): bool
    {
        return count($this->export->headings()) > self::LANDSCAPE_COLUMN_THRESHOLD;
    }

    public function footerNote(): string
    {
        return Setting::brandInstansi().' · '.$this->export->title();
    }

    public function data(): array
    {
        $columns = $this->export->columns();

        return [
            'title' => $this->export->title(),
            'subtitle' => $this->export->subtitle(),
            'instansi' => Setting::brandInstansi(),
            'aplikasi' => Setting::brandNama(),
            'logo' => $this->logoPath(),
            'generatedAt' => now(),
            'columns' => $columns,
            'summary' => $this->export->summary(),
            'rows' => $this->renderedRows($columns),
        ];
    }

    /**
     * Baris data yang nilainya sudah diformat sesuai tipe tiap kolom, jadi view
     * cukup mencetaknya. Sengaja dikumpulkan ke dalam array: laporan PDF dirender
     * sekali jalan oleh Chromium sehingga tidak bisa memakai aliran malas.
     *
     * @param  list<array{key: string, label: string, format: EnumFormatKolom}>  $columns
     * @return list<list<array{text: string, align: string, nowrap: bool, badge: bool, aktif: bool, kosong: bool}>>
     */
    protected function renderedRows(array $columns): array
    {
        $rendered = [];

        foreach ($this->export->rows() as $row) {
            $values = array_values(is_array($row) ? $row : iterator_to_array($row));
            $cells = [];

            foreach ($columns as $index => $column) {
                $value = $values[$index] ?? null;

                $format = $column['format'];
                $kosong = blank($value) && ! is_numeric($value);

                $cells[] = [
                    'text' => $format->tampilkan($value),
                    'align' => $format->perataan(),
                    'nowrap' => $format->isNowrap(),
                    'badge' => $format->isBadge() && ! $kosong,
                    'aktif' => $format->isBadge() && filter_var($value, FILTER_VALIDATE_BOOLEAN),
                    'kosong' => $kosong,
                ];
            }

            $rendered[] = $cells;
        }

        return $rendered;
    }

    /**
     * Path absolut logo brand di disk, siap disematkan sebagai data URI. Logo yang
     * tidak dipasang atau hilang dari disk membuat kop tampil tanpa gambar.
     */
    protected function logoPath(): ?string
    {
        $path = Setting::brandLogoPath();

        if (blank($path)) {
            return null;
        }

        $absolute = Storage::disk(Setting::BRAND_LOGO_DISK)->path($path);

        return is_file($absolute) ? $absolute : null;
    }
}
