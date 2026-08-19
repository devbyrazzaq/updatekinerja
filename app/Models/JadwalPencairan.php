<?php

namespace App\Models;

use App\Enums\EnumMetodePembayaran;
use App\Enums\EnumStatusPencairan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Satu gelombang pencairan anggaran, mis. "Pencairan Awal Bulan Januari" pada
 * tanggal tertentu. Realisasi yang lolos verifikasi Biro Keuangan dijadwalkan ke
 * salah satu jadwal ini, sehingga total anggaran yang akan cair pada satu tanggal
 * terlihat sebagai satu angka.
 */
class JadwalPencairan extends Model
{
    use HasUuids;

    /**
     * Hanya kolom uuid yang menerima nilai UUID otomatis; primary key id tetap
     * auto-increment.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'tahun_kerja_id',
        'name',
        'tanggal_pencairan',
        'status',
        'catatan',
        'dicairkan_at',
        'keuangan_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_pencairan' => 'date',
            'status' => EnumStatusPencairan::class,
            'dicairkan_at' => 'datetime',
        ];
    }

    public function tahunKerja(): BelongsTo
    {
        return $this->belongsTo(TahunKerja::class);
    }

    public function keuangan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'keuangan_id');
    }

    /** @return HasMany<RealisasiProgramKerja, $this> */
    public function realisasiProgramKerjas(): HasMany
    {
        return $this->hasMany(RealisasiProgramKerja::class);
    }

    /**
     * Realisasi yang anggarannya belum diserahkan pada jadwal ini.
     *
     * @return HasMany<RealisasiProgramKerja, $this>
     */
    public function realisasiBelumDicairkan(): HasMany
    {
        return $this->realisasiProgramKerjas()->whereNull('dicairkan_at');
    }

    /**
     * Total anggaran yang akan dicairkan pada jadwal ini: jumlah nominal setiap
     * realisasi yang dijadwalkan padanya.
     */
    public function totalNominal(): float
    {
        if ($this->relationLoaded('realisasiProgramKerjas')) {
            return (float) $this->realisasiProgramKerjas
                ->sum(fn (RealisasiProgramKerja $realisasi): float => $realisasi->nominalPencairan());
        }

        return (float) $this->realisasiProgramKerjas()
            ->selectRaw('coalesce(sum(coalesce(nominal_disetujui, nominal_diajukan, anggaran_digunakan)), 0) as total')
            ->value('total');
    }

    public function jumlahRealisasi(): int
    {
        return $this->relationLoaded('realisasiProgramKerjas')
            ? $this->realisasiProgramKerjas->count()
            : $this->realisasiProgramKerjas()->count();
    }

    public function sudahDicairkan(): bool
    {
        return $this->status === EnumStatusPencairan::Dicairkan;
    }

    /**
     * Label pilihan pada select penjadwalan, mis.
     * "Pencairan Awal Bulan Januari — 05 Januari 2026".
     */
    public function labelPilihan(): string
    {
        return $this->name.' — '.$this->tanggal_pencairan?->locale('id')->translatedFormat('d F Y');
    }

    /**
     * Realisasi pada jadwal ini yang cara pembayarannya belum lengkap, mis. transfer
     * yang rekening tujuannya belum ditentukan. Selama masih ada, jadwal tidak dapat
     * dicairkan sekaligus.
     *
     * @return Collection<int, RealisasiProgramKerja>
     */
    public function realisasiBelumSiapDicairkan(): Collection
    {
        return $this->realisasiBelumDicairkan()
            ->get()
            ->reject(fn (RealisasiProgramKerja $realisasi): bool => $realisasi->siapDicairkan())
            ->values();
    }

    /**
     * Ringkasan cara pembayaran anggotanya, mis. "2 transfer, 1 tunai".
     */
    public function ringkasanMetodePembayaran(): string
    {
        $jumlahPerMetode = $this->realisasiProgramKerjas()
            ->selectRaw('metode_pembayaran, count(*) as jumlah')
            ->groupBy('metode_pembayaran')
            ->pluck('jumlah', 'metode_pembayaran');

        $bagian = [];

        foreach ($jumlahPerMetode as $metode => $jumlah) {
            $label = EnumMetodePembayaran::tryFrom((string) $metode)?->getLabel() ?? 'belum ditentukan';
            $bagian[] = $jumlah.' '.mb_strtolower($label);
        }

        return $bagian === [] ? 'belum ada realisasi' : implode(', ', $bagian);
    }

    /**
     * Mencairkan seluruh realisasi pada jadwal ini yang anggarannya belum diserahkan,
     * masing-masing memakai cara pembayaran yang sudah dipilih saat penjadwalan.
     * Mengembalikan jumlah realisasi yang ikut dicairkan.
     */
    public function cairkan(?int $userId = null): int
    {
        $realisasis = $this->realisasiBelumDicairkan()->get();

        foreach ($realisasis as $realisasi) {
            $realisasi->tandaiAnggaranDicairkan($userId);
        }

        $this->update([
            'status' => EnumStatusPencairan::Dicairkan,
            'dicairkan_at' => $this->dicairkan_at ?? now(),
            'keuangan_id' => $userId ?? $this->keuangan_id,
        ]);

        return $realisasis->count();
    }

    /**
     * Menyesuaikan status jadwal dengan realisasi anggotanya: dianggap sudah
     * dicairkan hanya bila seluruh realisasinya sudah menerima anggaran.
     */
    public function segarkanStatus(): void
    {
        $jumlah = $this->realisasiProgramKerjas()->count();
        $belumCair = $this->realisasiProgramKerjas()->whereNull('dicairkan_at')->count();
        $selesai = $jumlah > 0 && $belumCair === 0;

        $this->update([
            'status' => $selesai ? EnumStatusPencairan::Dicairkan : EnumStatusPencairan::Dijadwalkan,
            'dicairkan_at' => $selesai ? ($this->dicairkan_at ?? now()) : null,
        ]);
    }

    /**
     * Jadwal yang masih dapat menerima penjadwalan realisasi baru: anggarannya
     * belum diserahkan sehingga jadwalnya belum ditutup.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeBelumDicairkan(Builder $query): Builder
    {
        return $query->where('status', '!=', EnumStatusPencairan::Dicairkan->value);
    }
}
