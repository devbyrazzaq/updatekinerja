<?php

namespace App\Models;

use Database\Factories\AcuanProgramKerjaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class AcuanProgramKerja extends Model
{
    /** @use HasFactory<AcuanProgramKerjaFactory> */
    use HasFactory, HasSlug;

    protected $fillable = [
        'kelompok_acuan_id',
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

    public function kelompokAcuan(): BelongsTo
    {
        return $this->belongsTo(KelompokAcuan::class);
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

    /** @return HasMany<AcuanTarget, $this> */
    public function targets(): HasMany
    {
        return $this->hasMany(AcuanTarget::class);
    }

    /**
     * Target pada tahun tertentu, siap tampil (mis. "90 persen").
     */
    public function targetTahun(int $tahun): ?string
    {
        return $this->targets
            ->firstWhere('tahun', $tahun)
            ?->label();
    }

    /** @return HasMany<PenawaranProgramKerja, $this> */
    public function penawaranProgramKerjas(): HasMany
    {
        return $this->hasMany(PenawaranProgramKerja::class);
    }
}
