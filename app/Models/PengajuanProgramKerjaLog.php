<?php

namespace App\Models;

use App\Enums\EnumStatusPengajuan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanProgramKerjaLog extends Model
{
    /**
     * Batas waktu (menit) sejak komentar dikirim untuk masih boleh diedit.
     */
    public const int MENIT_BATAS_EDIT_KOMENTAR = 30;

    protected $fillable = [
        'pengajuan_program_kerja_id',
        'user_id',
        'status',
        'description',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnumStatusPengajuan::class,
            'properties' => 'array',
        ];
    }

    public function pengajuanProgramKerja(): BelongsTo
    {
        return $this->belongsTo(PengajuanProgramKerja::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Isi komentar yang tersimpan pada log ini, bila ada.
     */
    public function catatan(): ?string
    {
        return $this->properties['catatan'] ?? null;
    }

    public function sudahDihapus(): bool
    {
        return (bool) ($this->properties['dihapus'] ?? false);
    }

    public function milikPengguna(?int $userId): bool
    {
        return $userId !== null && $this->user_id === $userId;
    }

    /**
     * Komentar hanya boleh dihapus oleh pemiliknya dan selama belum dihapus.
     */
    public function komentarDapatDiubahOleh(?int $userId): bool
    {
        return $this->milikPengguna($userId) && ! $this->sudahDihapus();
    }

    /**
     * Komentar hanya boleh diedit oleh pemiliknya selama belum dihapus dan
     * masih dalam batas waktu penyuntingan sejak komentar dikirim.
     */
    public function komentarDapatDieditOleh(?int $userId): bool
    {
        return $this->komentarDapatDiubahOleh($userId)
            && $this->created_at?->diffInMinutes(now()) < self::MENIT_BATAS_EDIT_KOMENTAR;
    }

    /**
     * Memperbarui isi komentar sekaligus menandai waktu penyuntingan.
     */
    public function ubahKomentar(string $catatan): void
    {
        $properties = $this->properties;
        $properties['catatan'] = $catatan;
        $properties['diedit_at'] = now()->toIso8601String();

        $this->update(['properties' => $properties]);
    }

    /**
     * Menandai komentar sebagai dihapus tanpa menghapus lognya, sehingga jejaknya
     * tetap ada sebagai penanda "pesan dihapus".
     */
    public function tandaiKomentarDihapus(): void
    {
        $properties = $this->properties;
        $properties['dihapus'] = true;

        $this->update(['properties' => $properties]);
    }
}
