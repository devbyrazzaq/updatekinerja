<?php

namespace App\Models;

use Database\Factories\RekeningFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Rekening extends Model
{
    /** @use HasFactory<RekeningFactory> */
    use HasFactory, HasSlug;

    protected $fillable = [
        'code',
        'unit_kerja_id',
        'name',
        'description',
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

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    /** @return HasMany<AcuanProgramKerja, $this> */
    public function acuanProgramKerjas(): HasMany
    {
        return $this->hasMany(AcuanProgramKerja::class);
    }

    /** @return HasMany<PenawaranProgramKerja, $this> */
    public function penawaranProgramKerjas(): HasMany
    {
        return $this->hasMany(PenawaranProgramKerja::class);
    }
}
