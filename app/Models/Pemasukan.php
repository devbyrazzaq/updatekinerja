<?php

namespace App\Models;

use App\Enums\EnumJenisWaktuPemasukan;
use App\Enums\EnumStatusPemasukan;
use App\Enums\EnumSumberPemasukan;
use App\Enums\EnumTahapanPemasukan;
use Database\Factories\PemasukanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Pemasukan extends Model
{
    /** @use HasFactory<PemasukanFactory> */
    use HasFactory;

    protected $fillable = [
        'unit_kerja_id',
        'user_id',
        'sumber',
        'pengajuan_program_kerja_id',
        'realisasi_program_kerja_id',
        'rincian_kegiatan',
        'jenis_waktu',
        'tanggal_pelaksanaan',
        'tanggal_selesai',
        'nominal_pendapatan',
        'keterangan',
        'status',
        'catatan_verifikasi',
        'disetujui_rektor_at',
        'rektor_id',
        'disetujui_wakil_at',
        'wakil_id',
        'disetujui_keuangan_at',
        'keuangan_id',
        'bukti_path',
        'bukti_original_names',
        'bukti_diserahkan_at',
        'divalidasi_at',
    ];

    protected function casts(): array
    {
        return [
            'sumber' => EnumSumberPemasukan::class,
            'jenis_waktu' => EnumJenisWaktuPemasukan::class,
            'status' => EnumStatusPemasukan::class,
            'tanggal_pelaksanaan' => 'date',
            'tanggal_selesai' => 'date',
            'nominal_pendapatan' => 'decimal:2',
            'bukti_path' => 'array',
            'bukti_original_names' => 'array',
            'disetujui_rektor_at' => 'datetime',
            'disetujui_wakil_at' => 'datetime',
            'disetujui_keuangan_at' => 'datetime',
            'bukti_diserahkan_at' => 'datetime',
            'divalidasi_at' => 'datetime',
        ];
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function pengajuanProgramKerja(): BelongsTo
    {
        return $this->belongsTo(PengajuanProgramKerja::class);
    }

    public function realisasiProgramKerja(): BelongsTo
    {
        return $this->belongsTo(RealisasiProgramKerja::class);
    }

    /**
     * Pencatat pemasukan ini, penerima notifikasi revisi/penolakan/permintaan bukti.
     */
    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function rektor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rektor_id');
    }

    public function wakil(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wakil_id');
    }

    public function keuangan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'keuangan_id');
    }

    /** @return HasMany<PemasukanLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(PemasukanLog::class);
    }

    /**
     * Periode pelaksanaan sebagai satu teks: "12 Agustus 2026" untuk kegiatan sehari,
     * atau "12 – 15 Agustus 2026" untuk rentang (bulan dan tahun tidak diulang bila
     * keduanya sama).
     */
    public function labelPeriode(): string
    {
        $mulai = $this->tanggal_pelaksanaan;

        if ($mulai === null) {
            return '-';
        }

        $mulai = $mulai->locale('id');
        $selesai = $this->tanggal_selesai?->locale('id');

        if (! ($this->jenis_waktu?->isRentang() ?? false) || $selesai === null || $selesai->isSameDay($mulai)) {
            return $mulai->translatedFormat('d F Y');
        }

        $formatMulai = match (true) {
            ! $mulai->isSameYear($selesai) => 'd F Y',
            ! $mulai->isSameMonth($selesai) => 'd F',
            default => 'd',
        };

        return $mulai->translatedFormat($formatMulai).' – '.$selesai->translatedFormat('d F Y');
    }

    /**
     * Status maju terakhir sebelum pemasukan masuk cabang (Revisi/Ditolak), diambil
     * dari riwayat. Menjadi acuan posisi stepper dan tujuan pengajuan ulang.
     */
    public function statusSebelumCabang(): ?EnumStatusPemasukan
    {
        return $this->logs()
            ->whereNotIn('status', array_column(EnumStatusPemasukan::statusCabang(), 'value'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('status');
    }

    /**
     * Tahapan yang ditampilkan pada stepper. Status bercabang tidak menyimpan posisi
     * alurnya sendiri, sehingga tahapnya diambil dari status terakhir sebelum
     * percabangan agar penanda revisi/penolakan muncul tepat pada tahap tempat
     * keputusan itu dibuat.
     */
    public function tahapanStepper(): EnumTahapanPemasukan
    {
        if (! in_array($this->status, EnumStatusPemasukan::statusCabang(), true)) {
            return $this->status->tahapan();
        }

        return $this->statusSebelumCabang()?->tahapan() ?? $this->status->tahapan();
    }

    /**
     * Status tujuan saat pemasukan diajukan. Draf memulai alur dari verifikasi Rektor;
     * revisi kembali ke tahap tempat revisi diminta agar verifikasi yang sudah
     * disetujui tidak diulang dari awal.
     */
    public function statusTujuanPengajuan(): EnumStatusPemasukan
    {
        if ($this->status === EnumStatusPemasukan::Revisi) {
            return $this->statusSebelumCabang() ?? EnumStatusPemasukan::Diajukan;
        }

        return EnumStatusPemasukan::Diajukan;
    }

    /**
     * Label status untuk tampilan. Status Revisi diperjelas dengan tahap tempat revisi
     * diminta (mis. "Revisi Rektor") agar terlihat pada tahap mana pemasukan
     * dikembalikan.
     */
    public function labelStatus(): string
    {
        if ($this->status !== EnumStatusPemasukan::Revisi) {
            return $this->status->getLabel();
        }

        return match ($this->tahapanStepper()) {
            EnumTahapanPemasukan::VerifikasiRektor => 'Revisi Rektor',
            EnumTahapanPemasukan::VerifikasiWakil => 'Revisi Wakil Rektor',
            EnumTahapanPemasukan::VerifikasiKeuangan => 'Revisi Biro Keuangan',
            default => 'Revisi',
        };
    }

    /**
     * Pemasukan boleh diajukan selama masih draf atau sedang diminta revisi.
     */
    public function dapatDiajukan(): bool
    {
        return in_array($this->status, [EnumStatusPemasukan::Draft, EnumStatusPemasukan::Revisi], true);
    }

    /**
     * Data pemasukan hanya boleh diubah/dihapus selama belum masuk antrian verifikasi.
     */
    public function dapatDiubah(): bool
    {
        return $this->dapatDiajukan();
    }

    /**
     * Mencatat sebuah peristiwa pada riwayat pemasukan sebagai kalimat naratif.
     * Waktu peristiwa mengikuti created_at log yang dibuat.
     */
    public function catatLog(EnumStatusPemasukan $status, ?int $userId, ?string $catatan = null, bool $diajukanKembali = false): PemasukanLog
    {
        $aktor = ($userId !== null ? User::find($userId)?->name : null) ?? 'Sistem';
        $kegiatan = $this->rincian_kegiatan ?? 'pemasukan';
        $waktu = Carbon::now()->locale('id');
        $waktuTeks = $waktu->translatedFormat('d F Y').' pukul '.$waktu->format('H.i');

        $deskripsi = match (true) {
            $diajukanKembali => "{$aktor} mengajukan kembali pemasukan \"{$kegiatan}\" untuk verifikasi pada {$waktuTeks}.",
            $status === EnumStatusPemasukan::Draft => "{$aktor} menyimpan pemasukan \"{$kegiatan}\" sebagai draf pada {$waktuTeks}.",
            $status === EnumStatusPemasukan::Diajukan => "{$aktor} mengajukan pemasukan \"{$kegiatan}\" untuk verifikasi pada {$waktuTeks}.",
            $status === EnumStatusPemasukan::VerifikasiWakil => "{$aktor} menyetujui pemasukan pada verifikasi Rektor dan meneruskannya ke Wakil Rektor pada {$waktuTeks}.",
            $status === EnumStatusPemasukan::VerifikasiKeuangan => "{$aktor} menyetujui pemasukan pada verifikasi Wakil Rektor dan meneruskannya ke Biro Keuangan pada {$waktuTeks}.",
            $status === EnumStatusPemasukan::MenungguBukti => "{$aktor} menyetujui pemasukan pada verifikasi Biro Keuangan pada {$waktuTeks}, unit kerja diminta mengunggah bukti tanda terima.",
            $status === EnumStatusPemasukan::Valid => "{$aktor} mengunggah bukti tanda terima pada {$waktuTeks} dan pemasukan dinyatakan valid.",
            $status === EnumStatusPemasukan::Revisi => "{$aktor} meminta revisi atas pemasukan pada {$waktuTeks}.",
            $status === EnumStatusPemasukan::Ditolak => "{$aktor} menolak pemasukan pada {$waktuTeks}.",
            default => "{$aktor} memperbarui status pemasukan \"{$kegiatan}\" menjadi {$status->getLabel()} pada {$waktuTeks}.",
        };

        if (filled($catatan)) {
            $deskripsi .= ' Catatan: '.$catatan;
        }

        return $this->logs()->create([
            'user_id' => $userId,
            'status' => $status,
            'description' => $deskripsi,
            'properties' => [
                'kegiatan' => $kegiatan,
                'aktor' => $aktor,
                'catatan' => filled($catatan) ? $catatan : null,
            ],
        ]);
    }
}
