<?php

namespace App\Exports;

/**
 * Ekspor sederhana untuk berkas template impor: judul kolom + beberapa baris
 * contoh yang nilainya diberikan langsung lewat konstruktor.
 */
class TemplateExport extends Export
{
    /**
     * @param  list<string>  $headings
     * @param  list<array<int, mixed>>  $rows
     * @param  array<string, string>  $labels
     */
    public function __construct(
        protected string $filename,
        protected array $headings,
        protected array $rows,
        protected array $labels = [],
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
        return 'Template Impor Data';
    }

    public function subtitle(): ?string
    {
        return 'Isi mulai baris di bawah contoh. Jangan mengubah baris key kolom '
            .'(baris kecil berhuruf miring) — baris itulah yang dibaca saat berkas diimpor.';
    }

    public function columnLabels(): array
    {
        return $this->labels;
    }
}
