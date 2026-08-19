<?php

namespace App\Reports;

use App\Enums\EnumFormatKolom;
use App\Exports\Export;
use App\Exports\Tautan;
use App\Models\Setting;

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

        // Satu kali jalan: barisnya bisa berupa aliran malas dari basis data, jadi
        // pita pembatas dikumpulkan bersamaan, bukan lewat penelusuran kedua.
        ['rows' => $rows, 'groups' => $groups] = $this->renderedRows($columns);

        return [
            'title' => $this->export->title(),
            'subtitle' => $this->export->subtitle(),
            'instansi' => Setting::brandInstansi(),
            'aplikasi' => Setting::brandNama(),
            'logo' => $this->logoDataUri(),
            'generatedAt' => now(),
            'columns' => $columns,
            'summary' => $this->export->summary(),
            'rows' => $rows,
            'groups' => $groups,
        ];
    }

    /**
     * Baris data yang nilainya sudah diformat sesuai tipe tiap kolom, jadi view
     * cukup mencetaknya. Sengaja dikumpulkan ke dalam array: laporan PDF dirender
     * sekali jalan oleh Chromium sehingga tidak bisa memakai aliran malas.
     *
     * `groups` menandai baris yang harus didahului pita pembatas — dikunci nomor
     * baris, bukan disisipkan ke dalam `rows`, supaya penomoran dan hitungan baris
     * pada view tetap menghitung data saja ({@see Export::groupLabel()}).
     *
     * @param  list<array{key: string, label: string, format: EnumFormatKolom}>  $columns
     * @return array{rows: list<list<array{text: string, url: ?string, align: string, nowrap: bool, badge: bool, aktif: bool, kosong: bool}>>, groups: array<int, string>}
     */
    protected function renderedRows(array $columns): array
    {
        $rendered = [];
        $groups = [];
        $currentGroup = null;

        foreach ($this->export->rows() as $row) {
            $values = array_values(is_array($row) ? $row : iterator_to_array($row));
            $cells = [];

            $group = $this->export->groupLabel($values);

            if ($group !== null && $group !== $currentGroup) {
                $groups[count($rendered)] = $group;
                $currentGroup = $group;
            }

            foreach ($columns as $index => $column) {
                $value = $values[$index] ?? null;

                $format = $column['format'];
                $kosong = blank($value) && ! is_numeric($value);

                $cells[] = [
                    'text' => $format->tampilkan($value),
                    // Laporan PDF ikut membawa tautannya: pembaca PDF modern
                    // mengklik anchor-nya sama seperti sel .xlsx.
                    'url' => $value instanceof Tautan ? $value->url : null,
                    'align' => $format->perataan(),
                    'nowrap' => $format->isNowrap(),
                    'badge' => $format->isBadge() && ! $kosong,
                    'aktif' => $format->isBadge() && filter_var($value, FILTER_VALIDATE_BOOLEAN),
                    'kosong' => $kosong,
                ];
            }

            $rendered[] = $cells;
        }

        return ['rows' => $rendered, 'groups' => $groups];
    }
}
