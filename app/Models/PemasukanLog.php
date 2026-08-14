<?php

namespace App\Models;

use App\Enums\EnumStatusPemasukan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemasukanLog extends Model
{
    protected $fillable = [
        'pemasukan_id',
        'user_id',
        'status',
        'description',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnumStatusPemasukan::class,
            'properties' => 'array',
        ];
    }

    public function pemasukan(): BelongsTo
    {
        return $this->belongsTo(Pemasukan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Isi catatan verifikator yang tersimpan pada log ini, bila ada.
     */
    public function catatan(): ?string
    {
        return $this->properties['catatan'] ?? null;
    }
}
