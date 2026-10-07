<?php

namespace App\Imports;

/**
 * Ringkasan hasil sebuah proses impor.
 *
 * Impor bersifat all-or-nothing: bila {@see $errors} tidak kosong, tidak ada baris
 * yang disimpan dan {@see $imported} bernilai 0.
 *
 * Berbeda dengan error, {@see $warnings} tidak membatalkan apa pun — isinya hal yang
 * tetap disimpan namun perlu diketahui pengimpor, mis. alokasi anggaran yang melampaui
 * pagu unit kerja.
 */
class ImportResult
{
    /**
     * @param  list<string>  $errors  Pesan validasi per baris/kolom yang gagal.
     * @param  list<string>  $warnings  Catatan atas baris yang tetap tersimpan.
     */
    public function __construct(
        public int $imported = 0,
        public array $errors = [],
        public array $warnings = [],
    ) {}

    public function failed(): bool
    {
        return $this->errors !== [];
    }

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }
}
