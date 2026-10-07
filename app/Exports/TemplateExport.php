<?php

namespace App\Exports;

/**
 * Ekspor sederhana untuk berkas template impor: judul kolom + beberapa baris
 * contoh yang nilainya diberikan langsung lewat konstruktor.
 *
 * Impor selalu membaca lembar pertama, jadi lembar tambahan yang dititipkan lewat
 * `$sheets` — mis. petunjuk pengisian dan daftar kode referensi — aman ikut serta
 * dalam berkas yang sama tanpa terbaca sebagai data.
 */
class TemplateExport extends Export
{
    /**
     * @param  list<string>  $headings
     * @param  list<array<int, mixed>>  $rows
     * @param  array<string, string>  $labels
     * @param  list<Export>  $sheets
     */
    public function __construct(
        protected string $filename,
        protected array $headings,
        protected array $rows,
        protected array $labels = [],
        protected ?string $subtitle = null,
        protected array $sheets = [],
        protected ?string $title = null,
    ) {}

    public function filename(): string
    {
        return $this->filename;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function rows(): iterable
    {
        return $this->rows;
    }

    public function title(): string
    {
        return $this->title ?? 'Template Impor Data';
    }

    public function subtitle(): ?string
    {
        return $this->subtitle ?? 'Isi mulai baris di bawah contoh. Jangan mengubah baris key kolom '
            .'(baris kecil berhuruf miring) — baris itulah yang dibaca saat berkas diimpor.';
    }

    public function columnLabels(): array
    {
        return $this->labels;
    }

    public function additionalSheets(): array
    {
        return $this->sheets;
    }
}
