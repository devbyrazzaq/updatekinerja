<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class KelompokAcuan extends Model
{
    use HasSlug;

    protected $fillable = [
        'name',
        'tahun_mulai',
        'tahun_selesai',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tahun_mulai' => 'integer',
            'tahun_selesai' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (KelompokAcuan $kelompokAcuan): void {
            if ($kelompokAcuan->is_active) {
                static::query()->whereKeyNot($kelompokAcuan->getKey())->where('is_active', true)->update(['is_active' => false]);
            }
        });
    }

    /**
     * Kelompok acuan yang sedang aktif (hanya satu).
     */
    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }

    /**
     * Daftar tahun yang dicakup kelompok ini, mis. [2025, 2026, 2027, 2028, 2029].
     * Jumlahnya mengikuti pengaturan sistem "jumlah tahun dalam 1 periode jabatan".
     *
     * @return array<int, int>
     */
    public function tahunList(): array
    {
        if ($this->tahun_mulai === null || $this->tahun_selesai === null || $this->tahun_selesai < $this->tahun_mulai) {
            return [];
        }

        return range($this->tahun_mulai, $this->tahun_selesai);
    }

    /**
     * Tahun yang dicakup kelompok aktif. Kosong bila belum ada kelompok aktif.
     *
     * @return array<int, int>
     */
    public static function activeTahunList(): array
    {
        return static::active()?->tahunList() ?? [];
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

    /** @return HasMany<AcuanProgramKerja, $this> */
    public function acuanProgramKerjas(): HasMany
    {
        return $this->hasMany(AcuanProgramKerja::class);
    }
}
