<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcuanTarget extends Model
{
    protected $fillable = [
        'acuan_program_kerja_id',
        'tahun',
        'nilai',
        'satuan',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
        ];
    }

    public function acuanProgramKerja(): BelongsTo
    {
        return $this->belongsTo(AcuanProgramKerja::class);
    }

    /**
     * Target siap tampil, mis. "90 persen".
     */
    public function label(): string
    {
        return trim($this->nilai.' '.($this->satuan ?? ''));
    }

    /**
     * Target untuk ditampilkan: angka pada nilai diformat desimal Indonesia agar
     * nominal uang terbaca, mis. "15000000 rupiah" menjadi "15.000.000 rupiah" dan
     * "1.79 persen" menjadi "1,79 persen". Awalan/akhiran non-angka seperti ">"
     * dipertahankan; nilai yang bukan angka ditampilkan apa adanya.
     */
    public function labelTampil(): string
    {
        return trim(static::formatNilai((string) $this->nilai).' '.($this->satuan ?? ''));
    }

    /**
     * Memformat bilangan pada teks target, mis. "20000000 rupiah" menjadi
     * "20.000.000 rupiah". Teks yang angkanya tidak tunggal atau sudah memakai koma
     * (mis. "0,4%") dikembalikan apa adanya.
     */
    public static function formatNilai(string $nilai): string
    {
        if (preg_match('/^(\D*?)(-?\d+)(?:\.(\d+))?(\D*)$/', trim($nilai), $bagian) !== 1) {
            return $nilai;
        }

        [, $awalan, $bulat, $pecahan, $akhiran] = $bagian;

        $angka = number_format((float) $bulat, 0, ',', '.');

        if ($pecahan !== '') {
            $angka .= ','.$pecahan;
        }

        return $awalan.$angka.$akhiran;
    }
}
