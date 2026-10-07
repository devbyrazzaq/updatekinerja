<?php

namespace App\Models;

use App\Enums\EnumStatusPengajuan;
use Database\Factories\PengajuanProgramKerjaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class PengajuanProgramKerja extends Model
{
    /** @use HasFactory<PengajuanProgramKerjaFactory> */
    use HasFactory, HasUuids;

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
        'penawaran_program_kerja_id',
        'unit_kerja_id',
        'user_id',
        'alokasi_anggaran',
        'deskripsi_kegiatan',
        'estimasi_mulai',
        'estimasi_selesai',
        'status',
        'catatan_verifikasi',
        'diverifikasi_at',
        'verifikator_id',
    ];

    protected function casts(): array
    {
        return [
            'alokasi_anggaran' => 'decimal:2',
            'estimasi_mulai' => 'datetime',
            'estimasi_selesai' => 'datetime',
            'status' => EnumStatusPengajuan::class,
            'diverifikasi_at' => 'datetime',
        ];
    }

    public function penawaranProgramKerja(): BelongsTo
    {
        return $this->belongsTo(PenawaranProgramKerja::class);
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifikator_id');
    }

    /** @return HasMany<RealisasiProgramKerja, $this> */
    public function realisasiProgramKerjas(): HasMany
    {
        return $this->hasMany(RealisasiProgramKerja::class);
    }

    /** @return HasMany<PengajuanProgramKerjaLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(PengajuanProgramKerjaLog::class);
    }

    /**
     * Pengajuan yang capaiannya boleh dicatat: pengajuannya sudah diterima, program
     * kerja induknya berada pada tahun kerja yang dipantau, dan unit kerjanya termasuk
     * cakupan data yang boleh diakses pengguna.
     *
     * Dipakai bersama oleh aksi Catat Capaian, impor capaian, dan lembar referensi
     * kode pada berkas templatenya, supaya ketiganya tidak pernah berbeda pendapat
     * soal program kerja mana yang tersedia.
     *
     * @param  Builder<static>  $query
     * @param  array<int, int>  $unitKerjaIds  Kosong berarti tidak ada satu pun yang lolos.
     * @return Builder<static>
     */
    public function scopeDapatDicatatCapaiannya(Builder $query, ?int $tahunKerjaId, array $unitKerjaIds): Builder
    {
        return $query
            ->where('status', EnumStatusPengajuan::Diterima->value)
            ->whereIn('unit_kerja_id', array_values(array_unique(array_map('intval', $unitKerjaIds))))
            ->whereHas('penawaranProgramKerja', fn (Builder $penawaran): Builder => $penawaran
                ->where('tahun_kerja_id', $tahunKerjaId));
    }

    /**
     * Mencatat sebuah peristiwa pada riwayat pengajuan sebagai kalimat naratif,
     * sekaligus menyimpan detail terstrukturnya di kolom properties. Waktu peristiwa
     * mengikuti created_at log yang dibuat.
     */
    public function catatLog(EnumStatusPengajuan $status, ?int $userId, ?string $catatan = null): PengajuanProgramKerjaLog
    {
        $aktor = ($userId !== null ? User::find($userId)?->name : null) ?? 'Sistem';
        $program = $this->penawaranProgramKerja?->name ?? 'program kerja';
        $nominal = Number::currency((float) $this->alokasi_anggaran, 'IDR', 'id');
        $waktu = Carbon::now()->locale('id');
        $waktuTeks = $waktu->translatedFormat('d F Y').' pukul '.$waktu->format('H.i');

        $deskripsi = match ($status) {
            EnumStatusPengajuan::Draft => "{$aktor} menyimpan pengajuan program kerja \"{$program}\" sebagai draf pada {$waktuTeks} dengan nominal {$nominal}.",
            EnumStatusPengajuan::Diajukan => "{$aktor} telah mengajukan pengajuan untuk program kerja \"{$program}\" pada {$waktuTeks} dengan nominal {$nominal}.",
            EnumStatusPengajuan::Diterima => "{$aktor} menyetujui pengajuan pada {$waktuTeks}.",
            EnumStatusPengajuan::Revisi => "{$aktor} meminta revisi atas pengajuan pada {$waktuTeks}.",
            EnumStatusPengajuan::Ditolak => "{$aktor} menolak pengajuan pada {$waktuTeks}.",
        };

        if (filled($catatan)) {
            $deskripsi .= " Catatan: {$catatan}";
        }

        return $this->logs()->create([
            'user_id' => $userId,
            'status' => $status,
            'description' => $deskripsi,
            'properties' => [
                'program_kerja' => $program,
                'unit_kerja' => $this->unitKerja?->name,
                'nominal' => (float) $this->alokasi_anggaran,
                'aktor' => $aktor,
                'catatan' => filled($catatan) ? $catatan : null,
            ],
        ]);
    }

    /**
     * Mencatat komentar dari pengguna pada riwayat pengajuan. Komentar boleh
     * ditambahkan kapan pun kecuali saat pengajuan sudah ditolak.
     */
    public function catatKomentar(string $catatan, ?int $userId): PengajuanProgramKerjaLog
    {
        $aktor = ($userId !== null ? User::find($userId)?->name : null) ?? 'Sistem';
        $waktu = Carbon::now()->locale('id');
        $waktuTeks = $waktu->translatedFormat('d F Y').' pukul '.$waktu->format('H.i');

        return $this->logs()->create([
            'user_id' => $userId,
            'status' => $this->status,
            'description' => "{$aktor} menambahkan komentar pada {$waktuTeks}.",
            'properties' => [
                'aktor' => $aktor,
                'catatan' => $catatan,
            ],
        ]);
    }

    /**
     * Label manusiawi untuk tiap kolom yang perubahannya dicatat pada log.
     *
     * @var array<string, string>
     */
    private const LABEL_PERUBAHAN = [
        'alokasi_anggaran' => 'alokasi anggaran',
        'deskripsi_kegiatan' => 'deskripsi kegiatan',
        'estimasi_mulai' => 'estimasi mulai',
        'estimasi_selesai' => 'estimasi selesai',
        'unit_kerja_id' => 'unit kerja',
        'penawaran_program_kerja_id' => 'program kerja',
    ];

    /**
     * Mencatat perubahan data pengajuan oleh pengguna pada riwayat. Khusus alokasi
     * anggaran dijelaskan nilai lama ke nilai barunya, kolom lain cukup disebut
     * judulnya. Mengembalikan null bila tidak ada kolom terlacak yang berubah.
     *
     * @param  array<string, array{lama: mixed, baru: mixed}>  $perubahan
     */
    public function catatPerubahan(array $perubahan, ?int $userId): ?PengajuanProgramKerjaLog
    {
        $frasa = [];
        $detail = [];

        foreach (self::LABEL_PERUBAHAN as $field => $label) {
            if (! array_key_exists($field, $perubahan)) {
                continue;
            }

            $lama = $perubahan[$field]['lama'] ?? null;
            $baru = $perubahan[$field]['baru'] ?? null;

            if ($field === 'alokasi_anggaran') {
                $nominalLama = Number::currency((float) $lama, 'IDR', 'id');
                $nominalBaru = Number::currency((float) $baru, 'IDR', 'id');
                $frasa[] = "{$label} dari {$nominalLama} menjadi {$nominalBaru}";
            } else {
                $frasa[] = $label;
            }

            $detail[$field] = ['lama' => $lama, 'baru' => $baru];
        }

        if ($frasa === []) {
            return null;
        }

        $aktor = ($userId !== null ? User::find($userId)?->name : null) ?? 'Sistem';
        $waktu = Carbon::now()->locale('id');
        $waktuTeks = $waktu->translatedFormat('d F Y').' pukul '.$waktu->format('H.i');

        $deskripsi = "{$aktor} mengubah ".$this->gabungFrasa($frasa)." pada {$waktuTeks}.";

        return $this->logs()->create([
            'user_id' => $userId,
            'status' => $this->status,
            'description' => $deskripsi,
            'properties' => [
                'aktor' => $aktor,
                'perubahan' => $detail,
            ],
        ]);
    }

    /**
     * Menggabungkan beberapa frasa menjadi satu kalimat berbahasa Indonesia,
     * memisahkan frasa terakhir dengan kata "dan".
     *
     * @param  array<int, string>  $frasa
     */
    private function gabungFrasa(array $frasa): string
    {
        if (count($frasa) === 1) {
            return $frasa[0];
        }

        $terakhir = array_pop($frasa);

        return implode(', ', $frasa).' dan '.$terakhir;
    }

    /**
     * Ketercapaian pengajuan mengikuti persentase realisasi terakhirnya, sehingga
     * setiap realisasi baru memperbarui capaian pengajuan. Tanpa realisasi, capaian
     * dianggap 0. Memakai relasi yang sudah dimuat bila tersedia agar hemat kueri.
     */
    public function persentaseKetercapaian(): int
    {
        $realisasiTerakhir = $this->relationLoaded('realisasiProgramKerjas')
            ? $this->realisasiProgramKerjas->sortByDesc('created_at')->first()
            : $this->realisasiProgramKerjas()->latest()->first();

        return (int) ($realisasiTerakhir?->persentase_ketercapaian ?? 0);
    }
}
