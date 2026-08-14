<?php

namespace App\Models;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusTahunKerja;
use App\Services\TransisiTahunKerja;
use Database\Factories\TahunKerjaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class TahunKerja extends Model
{
    /** @use HasFactory<TahunKerjaFactory> */
    use HasFactory, HasSlug;

    protected $fillable = [
        'periode_id',
        'kelompok_acuan_id',
        'name',
        'tahun',
        'status',
        'batas_anggaran',
        'referensi_tahun_kerja_id',
        'description',
        'start_datetime',
        'end_datetime',
        'ditutup_pada',
        'ditutup_oleh_id',
        'dikunci_pada',
        'dikunci_oleh_id',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'status' => EnumStatusTahunKerja::class,
            'batas_anggaran' => 'decimal:2',
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'ditutup_pada' => 'datetime',
            'dikunci_pada' => 'datetime',
        ];
    }

    /**
     * Tahun yang dipakai untuk memilih target Acuan Program Kerja saat penawaran
     * dibentuk. Tahun kerja lama yang belum mengisi kolom ini jatuh kembali ke tahun
     * mulai agar data tetap terbentuk.
     */
    public function tahunTarget(): ?int
    {
        return $this->tahun ?? $this->start_datetime?->year;
    }

    /**
     * Menjaga invariant dua slot konteks: paling banyak satu tahun Berjalan dan satu
     * tahun Perencanaan. Perpindahan status ditempuh lewat {@see TransisiTahunKerja}
     * yang memindahkan slot secara berurutan; hook ini hanya jaring pengaman terakhir.
     */
    protected static function booted(): void
    {
        static::saving(function (TahunKerja $tahunKerja): void {
            $status = $tahunKerja->status;

            if (! $status instanceof EnumStatusTahunKerja || ! $status->adalahSlotTunggal()) {
                return;
            }

            $pemegangLain = static::query()
                ->where('status', $status)
                ->when($tahunKerja->exists, fn (Builder $query): Builder => $query->whereKeyNot($tahunKerja->getKey()))
                ->first();

            if ($pemegangLain instanceof self) {
                throw new RuntimeException(
                    "Tahun kerja {$pemegangLain->name} sudah berstatus {$status->getLabel()}. Hanya satu tahun kerja yang boleh memegang status ini."
                );
            }
        });
    }

    /**
     * Tahun kerja yang sedang dijalankan (hanya satu).
     */
    public static function berjalan(): ?self
    {
        return static::query()->where('status', EnumStatusTahunKerja::Berjalan)->first();
    }

    /**
     * Tahun kerja yang sedang direncanakan untuk periode mendatang (hanya satu).
     */
    public static function perencanaan(): ?self
    {
        return static::query()->where('status', EnumStatusTahunKerja::Perencanaan)->first();
    }

    /**
     * Tahun kerja yang sudah tidak menerima pengajuan baru namun realisasinya masih
     * dituntaskan. Boleh lebih dari satu bila penguncian tertunda.
     *
     * @return Collection<int, self>
     */
    public static function penutupan(): Collection
    {
        return static::query()->where('status', EnumStatusTahunKerja::Penutupan)->get();
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

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    /**
     * Kelompok acuan yang dipasangkan dengan tahun kerja ini. Menjadikan tiap slot
     * konteks berdiri sendiri saat tahun berjalan dan tahun perencanaan berada di
     * RENSTRA yang berbeda.
     *
     * @return BelongsTo<KelompokAcuan, $this>
     */
    public function kelompokAcuan(): BelongsTo
    {
        return $this->belongsTo(KelompokAcuan::class);
    }

    /**
     * Tahun kerja lain yang dijadikan referensi anggaran untuk perbandingan pagu.
     *
     * @return BelongsTo<TahunKerja, $this>
     */
    public function referensiTahunKerja(): BelongsTo
    {
        return $this->belongsTo(TahunKerja::class, 'referensi_tahun_kerja_id');
    }

    /**
     * Pengguna yang mengakhiri tahun kerja ini.
     *
     * @return BelongsTo<User, $this>
     */
    public function ditutupOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditutup_oleh_id');
    }

    /**
     * Pengguna yang mengunci tahun kerja ini menjadi hanya-baca.
     *
     * @return BelongsTo<User, $this>
     */
    public function dikunciOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikunci_oleh_id');
    }

    /** @return HasMany<PaguAnggaran, $this> */
    public function paguAnggarans(): HasMany
    {
        return $this->hasMany(PaguAnggaran::class);
    }

    /** @return HasMany<PenawaranProgramKerja, $this> */
    public function penawaranProgramKerjas(): HasMany
    {
        return $this->hasMany(PenawaranProgramKerja::class);
    }

    /**
     * Jumlah program kerja yang ditawarkan pada tahun kerja ini.
     */
    public function jumlahProgramKerja(): int
    {
        return $this->penawaranProgramKerjas()->count();
    }

    /**
     * Pengajuan program kerja tahun kerja ini yang sudah benar-benar diajukan: draf
     * belum mengikat anggaran dan pengajuan ditolak melepaskannya kembali.
     *
     * @return Builder<PengajuanProgramKerja>
     */
    public function pengajuanBerjalan(): Builder
    {
        return PengajuanProgramKerja::query()
            ->whereNotIn('status', [EnumStatusPengajuan::Draft, EnumStatusPengajuan::Ditolak])
            ->whereHas(
                'penawaranProgramKerja',
                fn (Builder $query): Builder => $query->where('tahun_kerja_id', $this->getKey()),
            );
    }

    /**
     * Total pagu anggaran yang sudah dibagikan ke seluruh unit kerja pada tahun
     * kerja ini.
     */
    public function totalPaguAnggaran(): float
    {
        return (float) $this->paguAnggarans()->sum('amount');
    }

    /**
     * Total alokasi anggaran yang diajukan unit kerja pada tahun kerja ini.
     */
    public function totalPengajuanAnggaran(): float
    {
        return (float) $this->pengajuanBerjalan()->sum('alokasi_anggaran');
    }

    /**
     * Total anggaran yang sudah terpakai, dibaca dari realisasi program kerja.
     */
    public function totalAnggaranDigunakan(): float
    {
        return (float) RealisasiProgramKerja::query()
            ->whereIn('pengajuan_program_kerja_id', $this->pengajuanBerjalan()->select('id'))
            ->sum('anggaran_digunakan');
    }
}
