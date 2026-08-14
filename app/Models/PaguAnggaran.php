<?php

namespace App\Models;

use Database\Factories\PaguAnggaranFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaguAnggaran extends Model
{
    /** @use HasFactory<PaguAnggaranFactory> */
    use HasFactory;

    protected $fillable = [
        'tahun_kerja_id',
        'unit_kerja_id',
        'amount',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function tahunKerja(): BelongsTo
    {
        return $this->belongsTo(TahunKerja::class);
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }
}
