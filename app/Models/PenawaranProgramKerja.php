<?php

namespace App\Models;

use Database\Factories\PenawaranProgramKerjaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class PenawaranProgramKerja extends Model
{
    /** @use HasFactory<PenawaranProgramKerjaFactory> */
    use HasFactory, HasSlug;

    protected $fillable = [
        'acuan_program_kerja_id',
        'tahun_kerja_id',
        'unit_kerja_id',
        'bidang_id',
        'kategori_id',
        'program_id',
        'rekening_id',
        'name',
        'aktifitas',
        'indikator',
        'nilai_standar',
        'satuan_nilai_standar',
        'target',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function acuanProgramKerja(): BelongsTo
    {
        return $this->belongsTo(AcuanProgramKerja::class);
    }

    public function tahunKerja(): BelongsTo
    {
        return $this->belongsTo(TahunKerja::class);
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function bidang(): BelongsTo
    {
        return $this->belongsTo(Bidang::class);
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function rekening(): BelongsTo
    {
        return $this->belongsTo(Rekening::class);
    }

    /** @return HasMany<PengajuanProgramKerja, $this> */
    public function pengajuanProgramKerjas(): HasMany
    {
        return $this->hasMany(PengajuanProgramKerja::class);
    }
}
