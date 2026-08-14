<?php

namespace App\Models;

use Database\Factories\RekeningBankFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rekening bank tujuan pencairan anggaran. Rekening dapat dimiliki satu unit kerja
 * atau berlaku umum (tanpa unit kerja), dan satu unit kerja boleh menandai salah
 * satu rekeningnya sebagai rekening utama yang terpilih otomatis saat penjadwalan
 * pencairan.
 */
class RekeningBank extends Model
{
    /** @use HasFactory<RekeningBankFactory> */
    use HasFactory;

    protected $fillable = [
        'bank_id',
        'unit_kerja_id',
        'nomor_rekening',
        'atas_nama',
        'is_utama',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_utama' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Rekening utama bersifat tunggal per unit kerja, sehingga menandai satu rekening
     * sebagai utama otomatis melepas penanda pada rekening lain milik unit yang sama.
     */
    protected static function booted(): void
    {
        static::saved(function (self $rekening): void {
            if (! $rekening->is_utama) {
                return;
            }

            static::query()
                ->whereKeyNot($rekening->getKey())
                ->where('unit_kerja_id', $rekening->unit_kerja_id)
                ->where('is_utama', true)
                ->update(['is_utama' => false]);
        });
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    /** @return HasMany<RealisasiProgramKerja, $this> */
    public function realisasiProgramKerjas(): HasMany
    {
        return $this->hasMany(RealisasiProgramKerja::class);
    }

    /**
     * Label rekening siap tampil, mis. "BSI 1234567890 — a.n. Fakultas Teknik".
     */
    public function label(): string
    {
        return trim(($this->bank?->name ?? 'Bank').' '.$this->nomor_rekening).' — a.n. '.$this->atas_nama;
    }

    /**
     * Rekening milik unit kerja tertentu, ditambah rekening umum yang tidak terikat
     * unit mana pun.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUntukUnitKerja(Builder $query, ?int $unitKerjaId): Builder
    {
        return $query->where(fn (Builder $query): Builder => $query
            ->whereNull('unit_kerja_id')
            ->when($unitKerjaId !== null, fn (Builder $query): Builder => $query->orWhere('unit_kerja_id', $unitKerjaId)));
    }

    /** @param  Builder<self>  $query */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Rekening yang dipakai secara baku untuk unit kerja ini: rekening utama miliknya,
     * lalu rekening miliknya yang terbaru. Rekening umum tidak dipakai sebagai default
     * agar anggaran tidak diam-diam dikirim ke rekening unit lain.
     */
    public static function bawaanUntukUnitKerja(?int $unitKerjaId): ?self
    {
        if ($unitKerjaId === null) {
            return null;
        }

        return static::query()
            ->aktif()
            ->where('unit_kerja_id', $unitKerjaId)
            ->orderByDesc('is_utama')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Pilihan rekening untuk select penjadwalan/pencairan satu unit kerja.
     *
     * @return array<int, string>
     */
    public static function opsiUntukUnitKerja(?int $unitKerjaId): array
    {
        return static::query()
            ->aktif()
            ->untukUnitKerja($unitKerjaId)
            ->with(['bank', 'unitKerja'])
            ->orderByDesc('is_utama')
            ->get()
            ->mapWithKeys(fn (self $rekening): array => [
                $rekening->getKey() => $rekening->label()
                    .($rekening->unit_kerja_id === null ? ' (umum)' : ''),
            ])
            ->all();
    }
}
