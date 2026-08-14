<?php

namespace App\Models;

use App\Enums\EnumJenisDokumenRealisasi;
use Database\Factories\RealisasiDokumenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

class RealisasiDokumen extends Model
{
    /** @use HasFactory<RealisasiDokumenFactory> */
    use HasFactory;

    protected $fillable = [
        'realisasi_program_kerja_id',
        'type',
        'path',
        'name',
        'original_name',
        'size',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => EnumJenisDokumenRealisasi::class,
            'size' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    /**
     * Nama berkas untuk ditampilkan: nama asli unggahan bila tersedia, jika tidak
     * jatuh ke nama penyimpanan.
     */
    public function getNamaTampilanAttribute(): string
    {
        return $this->original_name ?: (string) $this->name;
    }

    /**
     * Ukuran berkas dalam satuan terbaca (KB/MB), atau null bila tidak diketahui.
     */
    public function getUkuranTerbacaAttribute(): ?string
    {
        return $this->size === null ? null : Number::fileSize($this->size, precision: 1);
    }

    public function realisasiProgramKerja(): BelongsTo
    {
        return $this->belongsTo(RealisasiProgramKerja::class);
    }
}
