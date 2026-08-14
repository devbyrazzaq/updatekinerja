<?php

namespace App\Models;

use Database\Factories\PeriodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Periode extends Model
{
    /** @use HasFactory<PeriodeFactory> */
    use HasFactory, HasSlug;

    protected $fillable = [
        'name',
        'description',
        'start_datetime',
        'end_datetime',
        'user_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Periode $periode): void {
            if ($periode->is_active) {
                static::query()->whereKeyNot($periode->getKey())->where('is_active', true)->update(['is_active' => false]);
            }
        });
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<TahunKerja, $this> */
    public function tahunKerjas(): HasMany
    {
        return $this->hasMany(TahunKerja::class);
    }
}
