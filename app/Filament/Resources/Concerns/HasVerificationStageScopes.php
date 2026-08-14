<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Membagi record satu tahap verifikasi menjadi tiga kelompok: perlu diverifikasi,
 * sudah direspon (setuju/revisi), dan ditolak.
 *
 * Karena status transaksi hanya satu kolom yang bergerak maju, atribusi outcome ke
 * tahap tertentu memakai kolom aktor: sebuah tahap dianggap "menangani" record bila
 * kolom aktornya terisi, dan penolakan diatribusikan ke tahap ini hanya bila aktor
 * tahap berikutnya belum terisi (alur bersifat sekuensial).
 */
trait HasVerificationStageScopes
{
    /**
     * Nilai status (string) yang berarti record masih menunggu tahap ini.
     *
     * @return array<int, string>
     */
    abstract public static function pendingStatuses(): array;

    /**
     * Kolom aktor yang terisi begitu tahap ini mengambil keputusan.
     */
    abstract public static function stageActorColumn(): string;

    /**
     * Kolom aktor tahap berikutnya (null bila tahap terakhir).
     */
    public static function nextStageActorColumn(): ?string
    {
        return null;
    }

    /**
     * Nilai status yang berarti ditolak (Pengajuan & Realisasi sama-sama 'ditolak').
     */
    public static function rejectedStatusValue(): string
    {
        return 'ditolak';
    }

    /**
     * Semua record yang pernah/masih berada di tahap ini (dasar query resource).
     */
    public static function applyStageScope(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereIn('status', static::pendingStatuses())
                ->orWhereNotNull(static::stageActorColumn());
        });
    }

    public static function applyPendingScope(Builder $query): Builder
    {
        return $query->whereIn('status', static::pendingStatuses());
    }

    public static function applyRespondedScope(Builder $query): Builder
    {
        return $query
            ->whereNotNull(static::stageActorColumn())
            ->whereNotIn('status', static::pendingStatuses())
            ->where(function (Builder $query): void {
                $query->where('status', '!=', static::rejectedStatusValue());

                if (static::nextStageActorColumn() !== null) {
                    $query->orWhereNotNull(static::nextStageActorColumn());
                }
            });
    }

    public static function applyRejectedScope(Builder $query): Builder
    {
        $query
            ->whereNotNull(static::stageActorColumn())
            ->where('status', static::rejectedStatusValue());

        if (static::nextStageActorColumn() !== null) {
            $query->whereNull(static::nextStageActorColumn());
        }

        return $query;
    }

    public static function pendingStageQuery(): Builder
    {
        return static::applyPendingScope(static::getEloquentQuery());
    }

    public static function respondedStageQuery(): Builder
    {
        return static::applyRespondedScope(static::getEloquentQuery());
    }

    public static function rejectedStageQuery(): Builder
    {
        return static::applyRejectedScope(static::getEloquentQuery());
    }

    /**
     * Apakah record masih menunggu keputusan tahap ini (untuk memunculkan aksi verifikasi).
     */
    public static function isPendingAtStage(Model $record): bool
    {
        $status = $record->getAttribute('status');

        return in_array($status instanceof \BackedEnum ? $status->value : $status, static::pendingStatuses(), true);
    }
}
