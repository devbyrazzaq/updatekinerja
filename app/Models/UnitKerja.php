<?php

namespace App\Models;

use App\Observers\UnitKerjaObserver;
use Database\Factories\UnitKerjaFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

#[ObservedBy(UnitKerjaObserver::class)]
class UnitKerja extends Model
{
    /** @use HasFactory<UnitKerjaFactory> */
    use HasFactory, HasSlug;

    protected $fillable = [
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

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<PaguAnggaran, $this> */
    public function paguAnggarans(): HasMany
    {
        return $this->hasMany(PaguAnggaran::class);
    }

    /** @return HasMany<AcuanProgramKerja, $this> */
    public function acuanProgramKerjas(): HasMany
    {
        return $this->hasMany(AcuanProgramKerja::class);
    }
}
