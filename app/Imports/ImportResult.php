<?php

namespace App\Imports;

/**
 * Ringkasan hasil sebuah proses impor.
 *
 * Impor bersifat all-or-nothing: bila {@see $errors} tidak kosong, tidak ada baris
 * yang disimpan dan {@see $imported} bernilai 0.
 */
class ImportResult
{
    /**
     * @param  list<string>  $errors  Pesan validasi per baris/kolom yang gagal.
     */
    public function __construct(
        public int $imported = 0,
        public array $errors = [],
    ) {}

    public function failed(): bool
    {
        return $this->errors !== [];
    }
}
