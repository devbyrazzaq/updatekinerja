<?php

namespace App\Models;

use App\Enums\EnumCaraPenyelesaianAnggaran;
use App\Enums\EnumJenisDokumenRealisasi;
use App\Enums\EnumJenisRealisasi;
use App\Enums\EnumMetodePembayaran;
use App\Enums\EnumStatusAnggaran;
use App\Enums\EnumStatusPencairan;
use App\Enums\EnumStatusPenyelesaianAnggaran;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Enums\EnumTahapanRealisasi;
use App\Enums\EnumUrgensiRealisasi;
use App\Services\TransisiTahunKerja;
use Database\Factories\RealisasiProgramKerjaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class RealisasiProgramKerja extends Model
{
    /** @use HasFactory<RealisasiProgramKerjaFactory> */
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
        'pengajuan_program_kerja_id',
        'name',
        'jenis_realisasi',
        'description',
        'proposal_path',
        'proposal_original_names',
        'start_datetime',
        'end_datetime',
        'anggaran_digunakan',
        'nominal_diajukan',
        'nominal_disetujui',
        'penentu_nominal_id',
        'status',
        'urgensi',
        'catatan_verifikasi',
        'disetujui_rektor_at',
        'rektor_id',
        'disetujui_wakil_at',
        'wakil_id',
        'status_pencairan',
        'jadwal_pencairan_id',
        'dicairkan_at',
        'metode_pembayaran',
        'rekening_bank_id',
        'keuangan_id',
        'laporan_path',
        'laporan_original_names',
        'evaluasi_pengerjaan',
        'status_anggaran',
        'nominal_selisih_anggaran',
        'status_penyelesaian_anggaran',
        'cara_penyelesaian_anggaran',
        'penyelesaian_anggaran_at',
        'persentase_ketercapaian',
        'laporan_diserahkan_at',
        'laporan_disetujui_at',
        'verifikator_laporan_id',
        'dicatat_oleh_id',
    ];

    /**
     * Realisasi baru berjenis beranggaran kecuali dinyatakan lain, sehingga instance
     * yang belum tersimpan pun sudah punya jenis yang bisa dibaca tampilan.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'jenis_realisasi' => EnumJenisRealisasi::Anggaran->value,
    ];

    protected function casts(): array
    {
        return [
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'anggaran_digunakan' => 'decimal:2',
            'nominal_diajukan' => 'decimal:2',
            'nominal_disetujui' => 'decimal:2',
            'jenis_realisasi' => EnumJenisRealisasi::class,
            'status' => EnumStatusRealisasi::class,
            'urgensi' => EnumUrgensiRealisasi::class,
            'proposal_path' => 'array',
            'proposal_original_names' => 'array',
            'laporan_path' => 'array',
            'laporan_original_names' => 'array',
            'disetujui_rektor_at' => 'datetime',
            'disetujui_wakil_at' => 'datetime',
            'status_pencairan' => EnumStatusPencairan::class,
            'dicairkan_at' => 'datetime',
            'metode_pembayaran' => EnumMetodePembayaran::class,
            'status_anggaran' => EnumStatusAnggaran::class,
            'nominal_selisih_anggaran' => 'decimal:2',
            'status_penyelesaian_anggaran' => EnumStatusPenyelesaianAnggaran::class,
            'cara_penyelesaian_anggaran' => EnumCaraPenyelesaianAnggaran::class,
            'penyelesaian_anggaran_at' => 'datetime',
            'persentase_ketercapaian' => 'integer',
            'laporan_diserahkan_at' => 'datetime',
            'laporan_disetujui_at' => 'datetime',
        ];
    }

    /**
     * Menyalin berkas proposal/laporan terbaru ke tabel dokumen agar satu
     * realisasi menyimpan riwayat lebih dari satu proposal maupun laporan.
     */
    protected static function booted(): void
    {
        static::saving(function (self $realisasi): void {
            // Selama laporan akhir belum diserahkan, `anggaran_digunakan` masih berarti
            // "nominal yang diajukan", jadi `nominal_diajukan` mengikutinya. Setelah laporan
            // masuk (anggaran_digunakan berubah menjadi realisasi akhir), nominal_diajukan
            // dikunci pada nilai terakhir sehingga persentase persetujuan tetap akurat.
            if ($realisasi->laporan_diserahkan_at === null) {
                $realisasi->nominal_diajukan = $realisasi->anggaran_digunakan;
            }
        });

        static::saved(function (self $realisasi): void {
            $disk = Storage::disk(config('filament.default_filesystem_disk'));

            $petaKolom = [
                'proposal_path' => ['jenis' => EnumJenisDokumenRealisasi::Proposal, 'namaAsli' => 'proposal_original_names'],
                'laporan_path' => ['jenis' => EnumJenisDokumenRealisasi::Laporan, 'namaAsli' => 'laporan_original_names'],
            ];

            foreach ($petaKolom as $kolom => $konfigurasi) {
                $paths = $realisasi->getAttribute($kolom);

                if (blank($paths) || (! $realisasi->wasRecentlyCreated && ! $realisasi->wasChanged($kolom))) {
                    continue;
                }

                $namaAsli = (array) $realisasi->getAttribute($konfigurasi['namaAsli']);

                foreach ((array) $paths as $path) {
                    if (blank($path)) {
                        continue;
                    }

                    $realisasi->dokumens()->firstOrCreate(
                        ['type' => $konfigurasi['jenis'], 'path' => $path],
                        [
                            'name' => basename((string) $path),
                            'original_name' => $namaAsli[$path] ?? basename((string) $path),
                            'size' => $disk->exists($path) ? $disk->size($path) : null,
                            'uploaded_at' => now(),
                        ],
                    );
                }
            }
        });
    }

    public function pengajuanProgramKerja(): BelongsTo
    {
        return $this->belongsTo(PengajuanProgramKerja::class);
    }

    /** @return HasMany<RealisasiDokumen, $this> */
    public function dokumens(): HasMany
    {
        return $this->hasMany(RealisasiDokumen::class);
    }

    /** @return HasMany<RealisasiDokumen, $this> */
    public function proposals(): HasMany
    {
        return $this->dokumens()->where('type', EnumJenisDokumenRealisasi::Proposal)->latest('uploaded_at');
    }

    /** @return HasMany<RealisasiDokumen, $this> */
    public function laporans(): HasMany
    {
        return $this->dokumens()->where('type', EnumJenisDokumenRealisasi::Laporan)->latest('uploaded_at');
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

    public function penentuNominal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penentu_nominal_id');
    }

    public function verifikatorLaporan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifikator_laporan_id');
    }

    /**
     * Pengguna yang mencatat capaian tanpa anggaran ini. Hanya terisi untuk realisasi
     * berjenis {@see EnumJenisRealisasi::TanpaAnggaran}; realisasi beranggaran
     * pengajunya diambil dari pengajuan program kerja induknya.
     */
    public function dicatatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh_id');
    }

    public function jadwalPencairan(): BelongsTo
    {
        return $this->belongsTo(JadwalPencairan::class);
    }

    public function rekeningBank(): BelongsTo
    {
        return $this->belongsTo(RekeningBank::class);
    }

    /** @return HasMany<RealisasiProgramKerjaLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(RealisasiProgramKerjaLog::class);
    }

    /**
     * Menjadwalkan pencairan anggaran realisasi ke sebuah jadwal pencairan, sekaligus
     * merencanakan cara pembayarannya (transfer ke rekening bank tertentu atau tunai).
     * Realisasi menunggu di status "Menunggu Anggaran Diberikan" sampai anggaran pada
     * jadwal itu benar-benar diserahkan; rencana pembayaran masih dapat diubah sampai
     * saat pencairan.
     */
    public function jadwalkanPencairan(
        JadwalPencairan $jadwal,
        ?int $userId = null,
        ?string $catatan = null,
        ?EnumMetodePembayaran $metode = null,
        ?int $rekeningBankId = null,
    ): void {
        $this->update([
            'jadwal_pencairan_id' => $jadwal->getKey(),
            'status_pencairan' => EnumStatusPencairan::Dijadwalkan,
            'status' => EnumStatusRealisasi::Dijadwalkan,
            'catatan_verifikasi' => $catatan ?? $this->catatan_verifikasi,
            'keuangan_id' => $userId ?? $this->keuangan_id,
            ...static::atributPembayaran($metode, $rekeningBankId),
        ]);

        $this->catatLog(EnumStatusRealisasi::Dijadwalkan, $userId, $catatan);

        $jadwal->segarkanStatus();
    }

    /**
     * Atribut rencana/realisasi pembayaran. Rekening hanya disimpan untuk pembayaran
     * transfer; pembayaran tunai melepas tautan rekening.
     *
     * @return array<string, mixed>
     */
    protected static function atributPembayaran(?EnumMetodePembayaran $metode, ?int $rekeningBankId): array
    {
        if ($metode === null) {
            return [];
        }

        return [
            'metode_pembayaran' => $metode,
            'rekening_bank_id' => $metode->isTransfer() ? $rekeningBankId : null,
        ];
    }

    /**
     * Mengeluarkan realisasi dari jadwal pencairannya sehingga kembali menunggu
     * penjadwalan di Biro Keuangan. Hanya berlaku selama anggaran belum diserahkan.
     */
    public function keluarkanDariJadwal(?int $userId = null): void
    {
        $jadwal = $this->jadwalPencairan;

        $this->update([
            'jadwal_pencairan_id' => null,
            'status_pencairan' => null,
            'status' => EnumStatusRealisasi::VerifikasiKeuangan,
        ]);

        $this->catatLog(EnumStatusRealisasi::VerifikasiKeuangan, $userId, 'Realisasi dikeluarkan dari jadwal pencairan dan menunggu penjadwalan ulang.');

        $jadwal?->segarkanStatus();
    }

    /**
     * Menandai anggaran realisasi sudah diserahkan ke unit kerja, beserta cara
     * penyerahannya (transfer ke rekening atau tunai), lalu realisasi berlanjut ke
     * tahap menunggu laporan pelaksanaan. Dipakai baik dari menu Verifikasi Biro
     * Keuangan maupun Jadwal Pencairan agar keduanya berperilaku sama.
     *
     * Bila metode dibiarkan null, rencana pembayaran yang dipilih saat penjadwalan
     * dipakai apa adanya.
     */
    public function tandaiAnggaranDicairkan(?int $userId = null, ?EnumMetodePembayaran $metode = null, ?int $rekeningBankId = null): void
    {
        $this->update([
            'status_pencairan' => EnumStatusPencairan::Dicairkan,
            'dicairkan_at' => now(),
            'status' => EnumStatusRealisasi::MenungguLaporan,
            'keuangan_id' => $userId ?? $this->keuangan_id,
            ...static::atributPembayaran($metode, $rekeningBankId),
        ]);

        $this->catatLog(EnumStatusRealisasi::MenungguLaporan, $userId, $this->keteranganPembayaran());

        $this->jadwalPencairan?->segarkanStatus();
    }

    /**
     * Membatalkan penandaan "anggaran sudah dicairkan": realisasi kembali menunggu
     * anggaran diberikan pada jadwal yang sama, dengan rencana pembayaran yang tetap
     * tersimpan. Hanya aman selama unit kerja belum menyerahkan laporan, lihat
     * {@see self::pencairanDapatDibatalkan()}.
     */
    public function batalkanPencairan(?int $userId = null, ?string $alasan = null): void
    {
        $this->update([
            'status_pencairan' => EnumStatusPencairan::Dijadwalkan,
            'dicairkan_at' => null,
            'status' => EnumStatusRealisasi::Dijadwalkan,
        ]);

        $this->catatPembatalanPencairan($alasan, $userId);
    }

    /**
     * Pencairan masih dapat dibatalkan selama realisasi baru sampai tahap menunggu
     * laporan dan unit kerja belum menyerahkan laporan apa pun. Setelah laporan masuk,
     * verifikasi laporan maupun penyelesaian selisih anggaran sudah bergantung pada
     * dana yang dianggap diterima.
     */
    public function pencairanDapatDibatalkan(): bool
    {
        return $this->dicairkan_at !== null
            && $this->status === EnumStatusRealisasi::MenungguLaporan
            && ! $this->sudahAdaLaporan();
    }

    /**
     * Realisasi siap dicairkan bila pembayaran tunai, atau transfer yang rekening
     * tujuannya sudah ditentukan.
     */
    public function siapDicairkan(): bool
    {
        if ($this->metode_pembayaran === null) {
            return false;
        }

        return ! $this->metode_pembayaran->isTransfer() || $this->rekening_bank_id !== null;
    }

    /**
     * Kalimat cara anggaran diserahkan, mis. "Dibayarkan via Transfer ke Rekening
     * (BSI 1234567890 — a.n. Unit A)". Null bila metode pembayaran belum dicatat.
     */
    public function keteranganPembayaran(): ?string
    {
        if ($this->metode_pembayaran === null) {
            return null;
        }

        $keterangan = 'Dibayarkan via '.$this->metode_pembayaran->getLabel();

        if (! $this->metode_pembayaran->isTransfer()) {
            return $keterangan.'.';
        }

        $rekening = $this->rekeningBank;

        return $rekening === null
            ? $keterangan.'.'
            : $keterangan.' ('.$rekening->label().').';
    }

    /**
     * Status bercabang yang keluar dari alur maju: tidak menyimpan posisi alurnya
     * sendiri sehingga tahapan/tujuan pengajuannya diturunkan dari riwayat.
     *
     * @return array<int, EnumStatusRealisasi>
     */
    private static function statusCabang(): array
    {
        return [EnumStatusRealisasi::Revisi, EnumStatusRealisasi::Ditolak, EnumStatusRealisasi::Dibatalkan];
    }

    /**
     * Status maju terakhir sebelum realisasi masuk cabang (Revisi/Ditolak/Dibatalkan),
     * diambil dari riwayat. Menjadi acuan posisi stepper dan tujuan pengajuan ulang.
     */
    public function statusSebelumCabang(): ?EnumStatusRealisasi
    {
        return $this->logs()
            ->whereNotIn('status', array_map(fn (EnumStatusRealisasi $s): string => $s->value, self::statusCabang()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('status');
    }

    /**
     * Tahapan yang ditampilkan pada stepper. Untuk status bercabang yang tidak menyimpan
     * posisi alurnya sendiri, tahapan diambil dari status terakhir sebelum percabangan
     * sehingga penanda revisi/penolakan/pembatalan muncul tepat pada tahap tempat
     * keputusan itu dibuat, bukan mengulang dari awal.
     */
    public function tahapanStepper(): EnumTahapanRealisasi
    {
        if (! in_array($this->status, self::statusCabang(), true)) {
            return $this->status->tahapan();
        }

        return $this->statusSebelumCabang()?->tahapan() ?? $this->status->tahapan();
    }

    /**
     * Capaian yang dicatat langsung dari Monitoring Program Kerja: tidak melewati
     * verifikasi berjenjang maupun pencairan, sehingga seluruh tampilan bernuansa
     * anggaran (besaran realisasi, persetujuan nominal, status penyerapan) tidak
     * berlaku baginya.
     */
    public function adalahTanpaAnggaran(): bool
    {
        return $this->jenis_realisasi?->tanpaAnggaran() ?? false;
    }

    /**
     * Tahapan yang dirender stepper untuk realisasi ini. Capaian tanpa anggaran hanya
     * melewati tiga langkah, sehingga delapan tahap alur beranggaran tidak ditampilkan
     * agar tidak terbaca seolah ada verifikasi dan pencairan yang terlewat.
     *
     * @return array<int, EnumTahapanRealisasi>
     */
    public function tahapanAlur(): array
    {
        if (! $this->adalahTanpaAnggaran()) {
            return EnumTahapanRealisasi::flowCases();
        }

        return [
            EnumTahapanRealisasi::Draf,
            EnumTahapanRealisasi::Pelaksanaan,
            EnumTahapanRealisasi::Selesai,
        ];
    }

    /**
     * Judul sebuah tahapan pada stepper, disesuaikan untuk capaian tanpa anggaran yang
     * memaknai tahapan yang sama secara berbeda.
     */
    public function labelTahapan(EnumTahapanRealisasi $tahapan): string
    {
        if (! $this->adalahTanpaAnggaran()) {
            return $tahapan->getLabel();
        }

        return match ($tahapan) {
            EnumTahapanRealisasi::Draf => 'Pencatatan Capaian',
            EnumTahapanRealisasi::Pelaksanaan => 'Laporan Diunggah',
            default => $tahapan->getLabel(),
        };
    }

    /**
     * Keterangan sebuah tahapan pada stepper. Tahap pencairan diperjelas dengan
     * tanggal anggaran benar-benar diserahkan bila sudah cair, agar terlihat kapan
     * unit kerja menerima dananya.
     */
    public function deskripsiTahapan(EnumTahapanRealisasi $tahapan): string
    {
        if ($this->adalahTanpaAnggaran()) {
            return match ($tahapan) {
                EnumTahapanRealisasi::Draf => 'Capaian dicatat langsung dari halaman Monitoring Program Kerja.',
                EnumTahapanRealisasi::Pelaksanaan => 'Laporan pelaksanaan kegiatan diunggah sebagai bukti capaian.',
                default => 'Ketercapaian target tercatat tanpa penggunaan anggaran.',
            };
        }

        if ($tahapan === EnumTahapanRealisasi::Pencairan && $this->dicairkan_at !== null) {
            return 'Anggaran dicairkan pada '.$this->dicairkan_at->locale('id')->translatedFormat('d F Y').'.';
        }

        return $tahapan->description();
    }

    /**
     * Status tujuan saat realisasi diajukan. Draf memulai alur dari verifikasi Rektor;
     * revisi kembali ke tahap tempat revisi diminta agar verifikasi yang sudah disetujui
     * (mis. Rektor) tidak diulang dari awal.
     */
    public function statusTujuanPengajuan(): EnumStatusRealisasi
    {
        if ($this->status === EnumStatusRealisasi::Revisi) {
            return $this->statusSebelumCabang() ?? EnumStatusRealisasi::Diajukan;
        }

        return EnumStatusRealisasi::Diajukan;
    }

    /**
     * Revisi yang diminta pada tahap verifikasi laporan, sehingga yang perlu diperbaiki
     * unit kerja adalah laporan realisasinya (lewat aksi "Perbaiki Laporan"), bukan
     * proposal pengajuannya.
     */
    public function adalahRevisiLaporan(): bool
    {
        return $this->status === EnumStatusRealisasi::Revisi
            && $this->tahapanStepper() === EnumTahapanRealisasi::VerifikasiLaporan;
    }

    /**
     * Label status untuk tampilan. Status Revisi diperjelas dengan tahap tempat revisi
     * diminta (mis. "Revisi Rektor") agar terlihat pada tahap mana realisasi dikembalikan.
     */
    public function labelStatus(): string
    {
        if ($this->status !== EnumStatusRealisasi::Revisi) {
            return $this->status->getLabel();
        }

        return match ($this->tahapanStepper()) {
            EnumTahapanRealisasi::VerifikasiRektor => 'Revisi Rektor',
            EnumTahapanRealisasi::VerifikasiWakil => 'Revisi Wakil Rektor',
            EnumTahapanRealisasi::VerifikasiKeuangan => 'Revisi Biro Keuangan',
            EnumTahapanRealisasi::VerifikasiLaporan => 'Revisi Laporan',
            default => 'Revisi',
        };
    }

    /**
     * Mencatat sebuah peristiwa pada riwayat realisasi sebagai kalimat naratif.
     * Waktu peristiwa mengikuti created_at log yang dibuat.
     */
    public function catatLog(EnumStatusRealisasi $status, ?int $userId, ?string $catatan = null, bool $diajukanKembali = false): RealisasiProgramKerjaLog
    {
        $aktor = ($userId !== null ? User::find($userId)?->name : null) ?? 'Sistem';
        $kegiatan = $this->name ?? 'realisasi';
        $waktu = Carbon::now()->locale('id');
        $waktuTeks = $waktu->translatedFormat('d F Y').' pukul '.$waktu->format('H.i');

        $deskripsi = match (true) {
            $diajukanKembali => "{$aktor} mengajukan kembali realisasi \"{$kegiatan}\" untuk verifikasi pada {$waktuTeks}.",
            $status === EnumStatusRealisasi::Draft => "{$aktor} menyimpan realisasi \"{$kegiatan}\" sebagai draf pada {$waktuTeks}.",
            $status === EnumStatusRealisasi::Diajukan => "{$aktor} mengajukan realisasi \"{$kegiatan}\" untuk verifikasi pada {$waktuTeks}.",
            $status === EnumStatusRealisasi::VerifikasiWakil => "{$aktor} menyetujui realisasi pada verifikasi Rektor dan meneruskannya ke Wakil Rektor pada {$waktuTeks}.",
            $status === EnumStatusRealisasi::VerifikasiKeuangan => "{$aktor} menyetujui realisasi pada verifikasi Wakil Rektor dan meneruskannya ke Biro Keuangan pada {$waktuTeks}.",
            $status === EnumStatusRealisasi::Dijadwalkan => "{$aktor} menjadwalkan pencairan anggaran realisasi pada {$waktuTeks}.",
            $status === EnumStatusRealisasi::MenungguLaporan => "{$aktor} menandai anggaran realisasi sudah dicairkan pada {$waktuTeks}, unit kerja diminta melaporkan pelaksanaan kegiatan.",
            $status === EnumStatusRealisasi::VerifikasiLaporan => "{$aktor} mengirim laporan realisasi untuk verifikasi pada {$waktuTeks}.",
            $status === EnumStatusRealisasi::Selesai => "{$aktor} menyetujui laporan dan menyelesaikan realisasi pada {$waktuTeks}.",
            $status === EnumStatusRealisasi::Ditolak => "{$aktor} menolak realisasi pada {$waktuTeks}.",
            $status === EnumStatusRealisasi::Revisi => "{$aktor} meminta revisi atas realisasi pada {$waktuTeks}.",
            $status === EnumStatusRealisasi::Dibatalkan => "{$aktor} membatalkan pengajuan realisasi \"{$kegiatan}\" pada {$waktuTeks}.",
            default => "{$aktor} memperbarui status realisasi \"{$kegiatan}\" menjadi {$status->getLabel()} pada {$waktuTeks}.",
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

    /**
     * Mencatat penetapan/perubahan nominal disetujui pada riwayat realisasi, menyebut
     * verifikator penentu, besaran nominal, dan persentasenya terhadap yang diajukan.
     */
    public function catatPenetapanNominal(float $nominal, ?int $userId): RealisasiProgramKerjaLog
    {
        $aktor = ($userId !== null ? User::find($userId)?->name : null) ?? 'Sistem';
        $waktu = Carbon::now()->locale('id');
        $waktuTeks = $waktu->translatedFormat('d F Y').' pukul '.$waktu->format('H.i');
        $nominalTeks = 'Rp '.number_format($nominal, 0, ',', '.');

        $diajukan = $this->nominalDiajukan();
        $keterangan = $diajukan > 0
            ? ' ('.((int) round($nominal / $diajukan * 100)).'% dari yang diajukan)'
            : '';

        return $this->logs()->create([
            'user_id' => $userId,
            'status' => $this->status,
            'description' => "{$aktor} menetapkan nominal disetujui sebesar {$nominalTeks}{$keterangan} pada {$waktuTeks}.",
            'properties' => [
                'aktor' => $aktor,
                'nominal_disetujui' => $nominal,
            ],
        ]);
    }

    /**
     * Mencatat penuntasan selisih anggaran pada riwayat realisasi. Peristiwa ini
     * terjadi setelah realisasi selesai, sehingga statusnya tidak berubah dan hanya
     * cara penyelesaiannya yang diceritakan.
     */
    public function catatPenyelesaianSelisih(EnumCaraPenyelesaianAnggaran $cara, ?int $userId): RealisasiProgramKerjaLog
    {
        $aktor = ($userId !== null ? User::find($userId)?->name : null) ?? 'Sistem';
        $waktu = Carbon::now()->locale('id');
        $waktuTeks = $waktu->translatedFormat('d F Y').' pukul '.$waktu->format('H.i');

        return $this->logs()->create([
            'user_id' => $userId,
            'status' => $this->status,
            'description' => "{$aktor} menuntaskan selisih anggaran pada {$waktuTeks} dengan cara: {$cara->getLabel()}. {$cara->getDescription()}",
            'properties' => [
                'aktor' => $aktor,
                'cara_penyelesaian_anggaran' => $cara->value,
                'nominal_selisih_anggaran' => (float) ($this->nominal_selisih_anggaran ?? 0),
            ],
        ]);
    }

    /**
     * Mencatat pembatalan penandaan "anggaran sudah dicairkan" (reset pencairan)
     * beserta alasannya pada riwayat realisasi.
     */
    public function catatPembatalanPencairan(?string $alasan, ?int $userId): RealisasiProgramKerjaLog
    {
        $aktor = ($userId !== null ? User::find($userId)?->name : null) ?? 'Sistem';
        $waktu = Carbon::now()->locale('id');
        $waktuTeks = $waktu->translatedFormat('d F Y').' pukul '.$waktu->format('H.i');
        $deskripsi = "{$aktor} membatalkan penandaan anggaran sudah dicairkan pada {$waktuTeks}, realisasi kembali menunggu anggaran diberikan.";

        return $this->logs()->create([
            'user_id' => $userId,
            'status' => EnumStatusRealisasi::Dijadwalkan,
            'description' => filled($alasan) ? $deskripsi.' Catatan: '.$alasan : $deskripsi,
            'properties' => [
                'aktor' => $aktor,
                'catatan' => filled($alasan) ? $alasan : null,
            ],
        ]);
    }

    /**
     * Mencatat komentar dari pengguna pada riwayat realisasi.
     */
    public function catatKomentar(string $catatan, ?int $userId): RealisasiProgramKerjaLog
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
     * Batas atas anggaran realisasi = alokasi anggaran pengajuan induk.
     */
    public function alokasiAnggaran(): string
    {
        return (string) ($this->pengajuanProgramKerja?->alokasi_anggaran ?? '0');
    }

    public function unitKerjaId(): ?int
    {
        return $this->pengajuanProgramKerja?->unit_kerja_id;
    }

    /**
     * Nominal yang diajukan unit kerja untuk realisasi ini. Diturunkan dari
     * `anggaran_digunakan` untuk data lama yang belum memiliki `nominal_diajukan`.
     */
    public function nominalDiajukan(): float
    {
        return (float) ($this->nominal_diajukan ?? $this->anggaran_digunakan);
    }

    /**
     * Nominal yang disetujui verifikator, atau null bila belum ada penetapan nominal.
     */
    public function nominalDisetujui(): ?float
    {
        return $this->nominal_disetujui !== null ? (float) $this->nominal_disetujui : null;
    }

    /**
     * Nominal yang akan/telah dicairkan untuk realisasi ini: nominal disetujui
     * verifikator, atau nominal yang diajukan bila penetapannya dilewati.
     */
    public function nominalPencairan(): float
    {
        return $this->nominalDisetujui() ?? $this->nominalDiajukan();
    }

    /**
     * Peranan verifikator yang menetapkan nominal disetujui (Rektor atau Wakil Rektor),
     * disimpulkan dari kecocokan `penentu_nominal_id` dengan aktor tiap tahap. Null bila
     * belum ada penetapan nominal.
     */
    public function perananPenentuNominal(): ?string
    {
        if ($this->penentu_nominal_id === null) {
            return null;
        }

        return match ($this->penentu_nominal_id) {
            $this->rektor_id => 'Rektor',
            $this->wakil_id => 'Wakil Rektor',
            default => null,
        };
    }

    /**
     * Persentase nominal disetujui terhadap nominal yang diajukan (dibulatkan).
     * Null bila belum ada nominal disetujui atau nominal diajukan nol.
     */
    public function persentasePersetujuan(): ?int
    {
        $disetujui = $this->nominalDisetujui();
        $diajukan = $this->nominalDiajukan();

        if ($disetujui === null || $diajukan <= 0) {
            return null;
        }

        return (int) round($disetujui / $diajukan * 100);
    }

    /**
     * Anggaran yang benar-benar diterima unit kerja untuk realisasi ini, yaitu
     * nominal yang disetujui verifikator (atau nominal yang diajukan bila penetapan
     * nominal dilewati). Menjadi acuan pembanding laporan penyerapan anggaran.
     */
    public function nominalDiterima(): float
    {
        return $this->nominalPencairan();
    }

    /**
     * Selisih anggaran yang diterima terhadap anggaran yang digunakan. Positif
     * berarti bersisa, negatif berarti melebihi anggaran yang diterima.
     */
    public function selisihAnggaran(?float $anggaranDigunakan = null): float
    {
        return $this->nominalDiterima() - ($anggaranDigunakan ?? (float) $this->anggaran_digunakan);
    }

    /**
     * Status penyerapan anggaran laporan: ada sisa, tergunakan semua, atau kurang.
     */
    public function hitungStatusAnggaran(?float $anggaranDigunakan = null): EnumStatusAnggaran
    {
        return EnumStatusAnggaran::fromPerbandingan(
            $this->nominalDiterima(),
            $anggaranDigunakan ?? (float) $this->anggaran_digunakan,
        );
    }

    /**
     * Anggaran yang digunakan sesuai status penyerapan dan besaran selisih yang
     * dilaporkan unit kerja: tergunakan semua berarti persis sebesar yang diterima,
     * bersisa menguranginya, dan kurang menambahnya.
     */
    public function anggaranDigunakanDariSelisih(EnumStatusAnggaran $statusAnggaran, float $selisih): float
    {
        $diterima = $this->nominalDiterima();

        return match ($statusAnggaran) {
            EnumStatusAnggaran::Habis => $diterima,
            EnumStatusAnggaran::Sisa => max(0.0, $diterima - $selisih),
            EnumStatusAnggaran::Kurang => $diterima + $selisih,
        };
    }

    /**
     * Penyesuaian anggaran unit kerja dari laporan realisasi ini: sisa yang sudah
     * dikembalikan menambah anggaran yang bisa digunakan, kekurangan yang sudah
     * dilunasi menguranginya. Selama selisih masih menunggu Biro Keuangan, tidak
     * ada penyesuaian.
     */
    public function penyesuaianAnggaran(): float
    {
        if (! ($this->status_penyelesaian_anggaran?->sudahSelesai() ?? false)) {
            return 0.0;
        }

        $selisih = (float) ($this->nominal_selisih_anggaran ?? 0);

        return match ($this->status_anggaran) {
            EnumStatusAnggaran::Sisa => $selisih,
            EnumStatusAnggaran::Kurang => -$selisih,
            default => 0.0,
        };
    }

    /**
     * Total penyesuaian anggaran seluruh realisasi milik satu unit kerja pada satu
     * tahun kerja: sisa yang dikembalikan menambah, kekurangan yang dilunasi
     * mengurangi anggaran yang bisa digunakan unit kerja tersebut.
     */
    public static function totalPenyesuaianAnggaran(int $unitKerjaId, int $tahunKerjaId): float
    {
        $dituntaskan = static::query()
            ->whereIn('status_penyelesaian_anggaran', EnumStatusPenyelesaianAnggaran::nilaiSelesai())
            ->whereHas('pengajuanProgramKerja', fn (Builder $query): Builder => $query
                ->where('unit_kerja_id', $unitKerjaId)
                ->whereHas('penawaranProgramKerja', fn (Builder $penawaran): Builder => $penawaran
                    ->where('tahun_kerja_id', $tahunKerjaId)));

        $dikembalikan = (float) (clone $dituntaskan)
            ->where('status_anggaran', EnumStatusAnggaran::Sisa->value)
            ->sum('nominal_selisih_anggaran');

        $dilunasi = (float) $dituntaskan
            ->where('status_anggaran', EnumStatusAnggaran::Kurang->value)
            ->sum('nominal_selisih_anggaran');

        return $dikembalikan - $dilunasi;
    }

    /**
     * Kalimat tindak lanjut selisih anggaran laporan, mis. "Anggaran bersisa
     * Rp 2.000.000 dan sudah dikembalikan." Null bila laporan belum menyebutkan
     * status anggarannya.
     */
    public function keteranganPenyelesaianAnggaran(): ?string
    {
        if ($this->status_anggaran === null) {
            return null;
        }

        if (! $this->status_anggaran->memerlukanSelisih()) {
            return 'Anggaran tergunakan semua, tidak ada sisa maupun kekurangan.';
        }

        $nominal = 'Rp '.number_format((float) ($this->nominal_selisih_anggaran ?? 0), 0, ',', '.');

        $dasar = $this->status_anggaran === EnumStatusAnggaran::Sisa
            ? "Anggaran bersisa {$nominal}"
            : "Anggaran kurang {$nominal}";

        return match ($this->status_penyelesaian_anggaran) {
            EnumStatusPenyelesaianAnggaran::Dikembalikan => "{$dasar} dan sudah dikembalikan ke Biro Keuangan.",
            EnumStatusPenyelesaianAnggaran::Dilunasi => "{$dasar} dan sudah dilunasi.",
            default => "{$dasar}, menunggu respons Biro Keuangan.",
        };
    }

    /**
     * Ketercapaian target terbaik yang sudah dicatat realisasi lain atas pengajuan
     * yang sama. Menjadi batas bawah ketercapaian laporan ini agar capaian program
     * kerja tidak pernah mundur; realisasi yang ditolak/dibatalkan diabaikan.
     */
    public function persentaseKetercapaianMinimum(): int
    {
        if ($this->pengajuan_program_kerja_id === null) {
            return 0;
        }

        return static::persentaseKetercapaianTertinggi($this->pengajuan_program_kerja_id, $this->getKey());
    }

    /**
     * Ketercapaian target tertinggi yang sudah tercatat atas sebuah pengajuan;
     * realisasi yang ditolak/dibatalkan diabaikan karena capaiannya tidak pernah
     * diakui. Sebuah realisasi dapat dikecualikan agar tidak menghitung dirinya
     * sendiri saat laporannya sedang disunting.
     */
    public static function persentaseKetercapaianTertinggi(int $pengajuanProgramKerjaId, ?int $kecualiRealisasiId = null): int
    {
        return (int) (static::query()
            ->where('pengajuan_program_kerja_id', $pengajuanProgramKerjaId)
            ->when($kecualiRealisasiId !== null, fn (Builder $query): Builder => $query->whereKeyNot($kecualiRealisasiId))
            ->whereNotIn('status', [EnumStatusRealisasi::Ditolak->value, EnumStatusRealisasi::Dibatalkan->value])
            ->max('persentase_ketercapaian') ?? 0);
    }

    /**
     * Target program kerja yang menjadi acuan laporan, diambil dari penawaran
     * program kerja induk pengajuan.
     *
     * @return array{target: ?string, indikator: ?string, nilai_standar: ?string, aktifitas: ?string}
     */
    public function targetProgramKerja(): array
    {
        $penawaran = $this->pengajuanProgramKerja?->penawaranProgramKerja;

        $nilaiStandar = trim(($penawaran?->nilai_standar ?? '').' '.($penawaran?->satuan_nilai_standar ?? ''));

        return [
            'target' => $penawaran?->target,
            'indikator' => $penawaran?->indikator,
            'nilai_standar' => $nilaiStandar !== '' ? $nilaiStandar : null,
            'aktifitas' => $penawaran?->aktifitas,
        ];
    }

    public function sudahAdaLaporan(): bool
    {
        return $this->laporan_diserahkan_at !== null;
    }

    /**
     * Apakah realisasi ini menyimpan berkas untuk satu jenis dokumen. Sengaja
     * membaca kolom path saja — tanpa menyentuh tabel dokumen — supaya aman dipanggil
     * per baris saat mengekspor ribuan realisasi.
     */
    public function punyaDokumen(EnumJenisDokumenRealisasi $jenis): bool
    {
        return collect((array) $this->getAttribute($jenis->value.'_path'))
            ->contains(fn ($path): bool => is_string($path) && filled($path));
    }

    /**
     * Berkas satu jenis dokumen, unggahan terbaru lebih dahulu. Sumber utamanya tabel
     * dokumen karena di sanalah nama asli, ukuran, dan waktu unggahnya tercatat;
     * kolom path dipakai sebagai jaring pengaman untuk berkas yang belum sempat
     * tersalin ke sana (mis. hasil impor data lama).
     *
     * @return Collection<int, array{path: string, nama: string, ukuran: ?string, diunggah: ?Carbon}>
     */
    public function berkasDokumen(EnumJenisDokumenRealisasi $jenis): Collection
    {
        $tercatat = $this->dokumens()
            ->where('type', $jenis)
            ->latest('uploaded_at')
            ->get()
            ->map(fn (RealisasiDokumen $dokumen): array => [
                'path' => (string) $dokumen->path,
                'nama' => $dokumen->nama_tampilan,
                'ukuran' => $dokumen->ukuran_terbaca,
                'diunggah' => $dokumen->uploaded_at,
            ]);

        $namaAsli = (array) $this->getAttribute($jenis->value.'_original_names');

        $belumTercatat = collect((array) $this->getAttribute($jenis->value.'_path'))
            ->filter(fn ($path): bool => is_string($path) && filled($path))
            ->reject(fn (string $path): bool => $tercatat->contains('path', $path))
            ->map(fn (string $path): array => [
                'path' => $path,
                'nama' => is_string($namaAsli[$path] ?? null) ? $namaAsli[$path] : basename($path),
                'ukuran' => null,
                'diunggah' => null,
            ]);

        return $tercatat->concat($belumTercatat)->values();
    }

    /**
     * Apa yang masih ditunggu dari realisasi ini, dipakai halaman Penyelesaian Tahun
     * Lalu agar terlihat siapa yang harus bergerak berikutnya. Alur yang masih
     * berjalan diceritakan lewat tahapannya; alur yang sudah selesai hanya menyisakan
     * selisih anggaran yang menunggu Biro Keuangan.
     */
    public function keteranganTunggakan(): string
    {
        if (! in_array($this->status, EnumStatusRealisasi::berjalan(), true)) {
            return 'Realisasi sudah selesai, tetapi selisih anggarannya masih menunggu tindak lanjut Biro Keuangan.';
        }

        if ($this->status === EnumStatusRealisasi::Revisi) {
            return 'Menunggu perbaikan unit kerja pada tahap '.$this->tahapanStepper()->getLabel().'.';
        }

        return $this->deskripsiTahapan($this->tahapanStepper());
    }

    /**
     * Realisasi milik satu unit kerja yang masih berjalan (sudah diajukan, belum
     * selesai/ditolak). Realisasi yang selesai membebaskan kembali kuotanya.
     *
     * Realisasi milik tahun kerja yang sudah Selesai tidak dihitung: tahun itu hanya
     * bisa dibaca, sehingga sisa realisasinya tidak mungkin dituntaskan dan tidak
     * boleh menyandera kuota selamanya.
     *
     * @return Builder<self>
     */
    public static function berjalanUntukUnit(int $unitKerjaId): Builder
    {
        return static::query()
            ->whereIn('status', array_column(EnumStatusRealisasi::berjalan(), 'value'))
            ->whereHas('pengajuanProgramKerja', fn (Builder $query) => $query->where('unit_kerja_id', $unitKerjaId))
            ->whereDoesntHave(
                'pengajuanProgramKerja.penawaranProgramKerja.tahunKerja',
                fn (Builder $query): Builder => $query->where('status', EnumStatusTahunKerja::Selesai->value),
            );
    }

    /**
     * Sisa kuota realisasi yang masih boleh diajukan unit kerja ini, mengikuti
     * pengaturan sistem "jumlah realisasi berjalan".
     */
    public static function sisaKuotaBerjalan(int $unitKerjaId): int
    {
        return max(0, Setting::maksRealisasiBerjalan() - static::berjalanUntukUnit($unitKerjaId)->count());
    }

    /**
     * Realisasi unit kerja ini yang masih berjalan padahal tahun kerjanya sudah
     * masuk Penutupan — tahun tepat sebelum tahun berjalan. Tunggakan seperti ini
     * dituntaskan lewat Penyelesaian Tahun Lalu, dan (bila diaktifkan di Pengaturan
     * Sistem) menahan pengajuan realisasi tahun kerja yang baru. Tahun yang lebih
     * lama dan sudah Selesai tidak lagi dihitung.
     *
     * @return Builder<self>
     */
    public static function tunggakanTahunLampau(int $unitKerjaId): Builder
    {
        return static::berjalanUntukUnit($unitKerjaId)->whereHas(
            'pengajuanProgramKerja.penawaranProgramKerja.tahunKerja',
            fn (Builder $query): Builder => $query->where('status', EnumStatusTahunKerja::Penutupan->value),
        );
    }

    /**
     * Seluruh tunggakan tahun kerja yang sudah ditinggalkan: realisasi tahun
     * berstatus Penutupan yang masih berjalan, ditambah laporan yang selisih
     * anggarannya masih menunggu Biro Keuangan.
     *
     * Dua hal inilah yang menahan penguncian tahun kerja
     * ({@see TransisiTahunKerja::penghambatPenguncian()}), sehingga
     * daftar ini persis pekerjaan yang tersisa sebelum tahun lama boleh ditutup.
     *
     * @return Builder<self>
     */
    public static function tunggakanTahunPenutupan(): Builder
    {
        return static::query()
            ->whereHas(
                'pengajuanProgramKerja.penawaranProgramKerja.tahunKerja',
                fn (Builder $query): Builder => $query->where('status', EnumStatusTahunKerja::Penutupan->value),
            )
            ->where(function (Builder $query): void {
                $query->whereIn('status', array_column(EnumStatusRealisasi::berjalan(), 'value'))
                    ->orWhere('status_penyelesaian_anggaran', EnumStatusPenyelesaianAnggaran::Menunggu->value);
            });
    }

    /**
     * Tahun kerja yang menaungi realisasi ini, diwarisi dari penawaran induk.
     */
    public function tahunKerja(): ?TahunKerja
    {
        return $this->pengajuanProgramKerja?->penawaranProgramKerja?->tahunKerja;
    }

    /**
     * Pengajuan ini meneruskan alur yang sudah berjalan — perbaikan atas permintaan
     * revisi — bukan pengajuan realisasi baru. Pembedaan ini penting saat tahun
     * kerjanya sudah masuk Penutupan: tahun itu menolak realisasi baru, tetapi
     * tunggakan yang sedang direvisi tetap harus bisa dituntaskan.
     */
    public function melanjutkanAlurBerjalan(): bool
    {
        return $this->status === EnumStatusRealisasi::Revisi;
    }

    /**
     * Tahun kerja realisasi ini masih menerima pengajuannya: tahun berjalan untuk
     * realisasi baru, dan sampai tahun Penutupan untuk perbaikan revisi.
     */
    public function tahunKerjaMenerimaPengajuan(): bool
    {
        $status = $this->tahunKerja()?->status;

        return $this->melanjutkanAlurBerjalan()
            ? ($status?->bolehPelaksanaan() ?? false)
            : ($status?->bolehRealisasiBaru() ?? false);
    }

    /**
     * Realisasi ini boleh diajukan bila tahun kerjanya masih menerima pengajuannya,
     * unit kerjanya tidak menunggak realisasi tahun sebelumnya (selama aturan itu
     * diaktifkan di Pengaturan Sistem), dan kuota realisasi berjalannya belum habis. Perbaikan revisi hanya diuji pada syarat pertama:
     * kuotanya sudah terpakai sejak pengajuan pertama, dan justru realisasi inilah
     * tunggakan yang sedang dituntaskan.
     */
    public function dapatDiajukan(): bool
    {
        $unitKerjaId = $this->unitKerjaId();

        if ($unitKerjaId === null) {
            return false;
        }

        if (! $this->tahunKerjaMenerimaPengajuan()) {
            return false;
        }

        if ($this->melanjutkanAlurBerjalan()) {
            return true;
        }

        if (Setting::blokirTunggakanTahunLalu() && static::tunggakanTahunLampau($unitKerjaId)->whereKeyNot($this->getKey())->exists()) {
            return false;
        }

        return static::berjalanUntukUnit($unitKerjaId)->whereKeyNot($this->getKey())->count() < Setting::maksRealisasiBerjalan();
    }
}
