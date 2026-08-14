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
}
