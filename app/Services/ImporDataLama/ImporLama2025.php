<?php

namespace App\Services\ImporDataLama;

use App\Enums\EnumJenisWaktuPemasukan;
use App\Enums\EnumMetodePembayaran;
use App\Enums\EnumModeGenerate;
use App\Enums\EnumRole;
use App\Enums\EnumStatusAnggaran;
use App\Enums\EnumStatusPemasukan;
use App\Enums\EnumStatusPencairan;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusPenyelesaianAnggaran;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumSumberPemasukan;
use App\Models\Bank;
use App\Models\JadwalPencairan;
use App\Models\KelompokAcuan;
use App\Models\Pemasukan;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\RealisasiDokumen;
use App\Models\RealisasiProgramKerja;
use App\Models\RekeningBank;
use App\Models\TahunKerja;
use App\Services\GeneratePenawaranFromAcuan;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Memindahkan data tahun kerja 2024 dan 2025 dari aplikasi LAMADU (basis data
 * `kinerja_2025`) ke skema sistem ini.
 *
 * Master datanya — unit kerja, bidang, kategori, acuan program kerja, pagu, dan
 * sebagian pengguna — sudah lebih dulu dipindahkan lewat seeder, sehingga kelas ini
 * fokus pada rantai transaksinya: pengajuan anggaran, realisasi program kerja,
 * dokumen proposal/laporan, jadwal pencairan, pemasukan, dan seluruh riwayatnya.
 *
 * Penautan ke master data memakai kunci alami (bukan id) karena id kedua aplikasi
 * tidak dijamin sama: acuan dicocokkan lewat unit kerja + nama kegiatan + indikator
 * dengan spasi yang dinormalkan, pengguna lewat {@see PencocokPengguna}.
 *
 * Impor bersifat idempoten: baris yang sudah pernah dipindahkan dikenali lewat kunci
 * alaminya sehingga perintah aman dijalankan ulang.
 */
class ImporLama2025
{
    public const KONEKSI = 'lama_2025';

    public const ARSIP_BERKAS = 'db/public-2025.zip';

    private ConnectionInterface $lama;

    /** @var array<int, int> id working_year lama → id tahun kerja baru */
    private array $petaTahunKerja = [];

    /** @var array<int, int> id work_program_offer lama → id penawaran baru */
    private array $petaPenawaran = [];

    /** @var array<int, int> id user lama → id user baru */
    private array $petaPengguna = [];

    /** @var array<int, string> id user lama → nama role di aplikasi lama */
    private array $peranPengguna = [];

    /** @var array<int, int> id distribution_schedule lama → id jadwal pencairan baru */
    private array $petaJadwal = [];

    /** @var array<int, int> id rekening lama → id rekening bank baru */
    private array $petaRekening = [];

    /** @var array<int, int> id budget_submission lama → id pengajuan baru */
    private array $petaPengajuan = [];

    /** @var array<string, string> path berkas pada arsip → path pada disk aplikasi */
    private array $petaBerkas = [];

    /** @var array<string, int> */
    private array $ringkasan = [];

    /** @var Closure(string): void */
    private Closure $lapor;

    public function __construct(
        private readonly PencocokPengguna $pengguna,
        private readonly SalinBerkasLama $berkas,
    ) {}

    /**
     * @param  Closure(string): void|null  $lapor  penerus pesan kemajuan ke konsol
     * @return array<string, int>
     */
    public function jalankan(bool $salinBerkas = true, ?string $arsip = null, ?Closure $lapor = null): array
    {
        $this->lapor = $lapor ?? static function (string $pesan): void {};
        $this->lama = DB::connection(self::KONEKSI);

        $this->siapkanTahunKerja();
        $this->siapkanPenawaran();
        $this->siapkanPengguna();
        $this->siapkanRekeningBank();

        if ($salinBerkas) {
            $this->salinBerkas($arsip ?? base_path(self::ARSIP_BERKAS));
        }

        DB::transaction(function (): void {
            $this->imporJadwalPencairan();
            $this->imporPengajuan();
            $this->imporRealisasi();
            $this->imporPemasukan();
        });

        // "Pengajuan langsung" adalah fitur LAMADU untuk permintaan dana di luar program
        // kerja. Sistem ini mensyaratkan tiap realisasi bertumpu pada program kerja yang
        // ditawarkan, sehingga baris tersebut tidak dipindahkan — jumlahnya dilaporkan
        // agar terlihat apa yang tertinggal.
        $this->ringkasan['pengajuan_langsung_tidak_dipindah'] = $this->lama->table('direct_submissions')->count();

        return $this->ringkasan;
    }

    /**
     * Menautkan tiap tahun kerja lama ke tahun kerja sistem ini lewat angka tahunnya.
     */
    private function siapkanTahunKerja(): void
    {
        $tahunKerjas = TahunKerja::all()->keyBy(fn (TahunKerja $tahunKerja): int => (int) $tahunKerja->tahun);

        foreach ($this->lama->table('working_years')->get() as $lama) {
            $tahun = (int) Str::of($lama->name)->match('/\d{4}/')->toString();
            $tahunKerja = $tahunKerjas->get($tahun);

            if ($tahunKerja === null) {
                throw new RuntimeException("Tahun kerja {$tahun} belum ada di sistem ini; jalankan seeder master data lebih dulu.");
            }

            $this->petaTahunKerja[(int) $lama->id] = $tahunKerja->id;
        }
    }

    /**
     * Memastikan tiap tahun kerja punya penawaran program kerja, lalu menautkan tiap
     * penawaran lama ke penawaran baru lewat acuan induknya.
     *
     * Tahun kerja lama (mis. 2024) belum pernah dibentuk penawarannya di sistem ini
     * karena seeder hanya menggarap tahun berjalan, jadi dibentuk di sini dari
     * kelompok acuan yang sama. Acuan tanpa target tahun tersebut ikut dibentuk
     * (`onlyWithTarget: false`) supaya seluruh penawaran lama menemukan padanannya.
     */
    private function siapkanPenawaran(): void
    {
        $kelompokAcuan = KelompokAcuan::query()->where('is_active', true)->orderBy('id')->first();

        if ($kelompokAcuan === null) {
            throw new RuntimeException('Kelompok acuan belum ada; jalankan seeder program kerja lebih dulu.');
        }

        foreach (array_unique($this->petaTahunKerja) as $tahunKerjaId) {
            $tahunKerja = TahunKerja::findOrFail($tahunKerjaId);

            if ($tahunKerja->kelompok_acuan_id === null) {
                $tahunKerja->update(['kelompok_acuan_id' => $kelompokAcuan->id]);
            }

            if (GeneratePenawaranFromAcuan::jumlahPenawaran($tahunKerja) > 0) {
                continue;
            }

            $hasil = app(GeneratePenawaranFromAcuan::class)->handle(
                $kelompokAcuan,
                $tahunKerja,
                EnumModeGenerate::Sinkron,
                onlyWithTarget: false,
            );

            $this->lapor->__invoke("Penawaran {$tahunKerja->name} dibentuk: {$hasil['created']} baru, {$hasil['updated']} diperbarui.");
            $this->ringkasan['penawaran_dibentuk'] = ($this->ringkasan['penawaran_dibentuk'] ?? 0) + $hasil['created'];
        }

        $petaAcuan = $this->petaAcuan();
        $penawarans = PenawaranProgramKerja::query()
            ->select(['id', 'acuan_program_kerja_id', 'tahun_kerja_id'])
            ->get()
            ->keyBy(fn (PenawaranProgramKerja $penawaran): string => $penawaran->acuan_program_kerja_id.':'.$penawaran->tahun_kerja_id);

        $tanpaPadanan = 0;

        foreach ($this->lama->table('work_program_offers')->get() as $offer) {
            $acuanId = $petaAcuan[(int) $offer->work_program_reference_id] ?? null;
            $tahunKerjaId = $this->petaTahunKerja[(int) $offer->working_year_id] ?? null;
            $penawaran = $acuanId !== null && $tahunKerjaId !== null
                ? $penawarans->get($acuanId.':'.$tahunKerjaId)
                : null;

            if ($penawaran === null) {
                $tanpaPadanan++;

                continue;
            }

            $this->petaPenawaran[(int) $offer->id] = $penawaran->id;
        }

        $this->ringkasan['penawaran_tertaut'] = count($this->petaPenawaran);
        $this->ringkasan['penawaran_tanpa_padanan'] = $tanpaPadanan;
    }

    /**
     * Peta acuan program kerja lama → baru, dicocokkan lewat unit kerja, nama
     * kegiatan, dan indikatornya. Spasi berlebih dinormalkan karena data hasil
     * seeder sudah dirapikan sedangkan dump aslinya belum.
     *
     * @return array<int, int>
     */
    private function petaAcuan(): array
    {
        $acuans = DB::table('acuan_program_kerjas')
            ->select(['id', 'unit_kerja_id', 'name', 'indikator'])
            ->get()
            ->keyBy(fn (object $acuan): string => $this->kunciAcuan($acuan->unit_kerja_id, $acuan->name, $acuan->indikator));

        $peta = [];

        foreach ($this->lama->table('work_program_references')->get() as $referensi) {
            $acuan = $acuans->get($this->kunciAcuan($referensi->departement_id, $referensi->activity, $referensi->indicator));

            if ($acuan !== null) {
                $peta[(int) $referensi->id] = (int) $acuan->id;
            }
        }

        return $peta;
    }

    private function kunciAcuan(int|string|null $unitKerjaId, ?string $nama, ?string $indikator): string
    {
        return implode('|', [
            (int) $unitKerjaId,
            $this->normalkan($nama),
            $this->normalkan($indikator),
        ]);
    }

    private function normalkan(?string $nilai): string
    {
        return Str::lower(trim((string) preg_replace('/\s+/u', ' ', (string) $nilai)));
    }

    /**
     * Menjodohkan seluruh akun aplikasi lama dengan akun sistem ini, membuat yang
     * belum ada, sekaligus mengingat peran tiap akun di aplikasi lama untuk menebak
     * siapa yang bertindak sebagai Rektor, Wakil Rektor, dan Biro Keuangan pada
     * riwayat verifikasi.
     */
    private function siapkanPengguna(): void
    {
        $unitKerja = $this->lama->table('assignments')
            ->pluck('departement_id', 'user_id');

        $peran = $this->lama->table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->pluck('roles.name', 'model_has_roles.model_id');

        $peranTambahan = $this->lama->table('multi_roles')
            ->join('roles', 'roles.id', '=', 'multi_roles.role_id')
            ->pluck('roles.name', 'multi_roles.user_id');

        $penggunaLama = collect($this->lama->table('users')->get())->map(function (object $user) use ($unitKerja, $peran, $peranTambahan): array {
            $peranLama = $peran[$user->id] ?? $peranTambahan[$user->id] ?? null;
            $this->peranPengguna[(int) $user->id] = (string) $peranLama;

            return [
                'id' => (int) $user->id,
                'username' => $user->memberId,
                'email' => $user->email,
                'nama' => (string) $user->name,
                'password' => $user->password,
                'aktif' => $user->status === 'active',
                'unit_kerja_id' => isset($unitKerja[$user->id]) ? (int) $unitKerja[$user->id] : null,
                'roles' => $this->rolesBaru($peranLama),
            ];
        });

        $this->petaPengguna = $this->pengguna->petakan($penggunaLama);
        $this->ringkasan['pengguna_dibuat'] = $this->pengguna->jumlahDibuat();
    }

    /**
     * Padanan role aplikasi lama pada sistem ini.
     *
     * @return array<int, string>
     */
    private function rolesBaru(?string $peranLama): array
    {
        $role = match ($peranLama) {
            'super-admin' => EnumRole::SuperAdmin,
            'admin' => EnumRole::Admin,
            'rektorat' => EnumRole::Rektor,
            'wakil-rektor-1', 'wakil-rektor-2', 'wakil-rektor-3' => EnumRole::WakilRektor,
            'keuangan' => EnumRole::BiroKeuangan,
            'pimpinan-unit' => EnumRole::PimpinanUnit,
            default => EnumRole::UnitKerja,
        };

        return [$role->value];
    }

    private function berperan(?int $userIdLama, string ...$peran): bool
    {
        return $userIdLama !== null && in_array($this->peranPengguna[$userIdLama] ?? '', $peran, true);
    }

    /**
     * Memindahkan rekening bank tujuan transfer beserta banknya. Kode bank lama hanya
     * dipakai bila belum ditempati bank lain karena kolomnya unik.
     */
    private function siapkanRekeningBank(): void
    {
        $banks = [];

        foreach ($this->lama->table('banks')->get() as $bankLama) {
            $bank = Bank::firstOrNew(['name' => $bankLama->nama]);

            if (! $bank->exists) {
                $kodeTerpakai = Bank::where('code', $bankLama->kode)->exists();
                $bank->fill([
                    'code' => $kodeTerpakai ? null : $bankLama->kode,
                    'is_active' => $bankLama->status === 'active',
                ])->save();
            }

            $banks[(int) $bankLama->id] = $bank->id;
        }

        foreach ($this->lama->table('rekenings')->get() as $rekeningLama) {
            $bankId = $banks[(int) $rekeningLama->bank_id] ?? null;

            if ($bankId === null) {
                continue;
            }

            $rekening = RekeningBank::firstOrCreate(
                ['bank_id' => $bankId, 'nomor_rekening' => $rekeningLama->nomor_rekening],
                [
                    'unit_kerja_id' => $rekeningLama->departement_id,
                    'atas_nama' => $rekeningLama->nama_rekening,
                    'is_active' => $rekeningLama->status === 'active',
                ],
            );

            $this->petaRekening[(int) $rekeningLama->id] = $rekening->id;
        }

        $this->ringkasan['rekening_bank'] = count($this->petaRekening);
    }

    /**
     * Menyalin dokumen proposal dan laporan dari arsip berkas publik aplikasi lama ke
     * disk privat sistem ini, sekaligus menyiapkan peta path lama → path baru yang
     * dipakai saat menulis kolom dokumen.
     */
    private function salinBerkas(string $arsip): void
    {
        $antrian = [];

        $daftar = [
            ['tabel' => 'realization_documents', 'direktori' => SalinBerkasLama::DIREKTORI_PROPOSAL],
            ['tabel' => 'realization_report_documents', 'direktori' => SalinBerkasLama::DIREKTORI_LAPORAN],
        ];

        foreach ($daftar as $sumber) {
            foreach ($this->lama->table($sumber['tabel'])->where('type', 'file')->get() as $dokumen) {
                $tujuan = $this->berkas->pathTujuan($sumber['direktori'], $dokumen->path);
                $this->petaBerkas[$dokumen->path] = $tujuan;
                $antrian[$tujuan] = $this->kandidatEntri($dokumen->path);
            }
        }

        $this->lapor->__invoke('Menyalin '.count($antrian).' berkas dokumen dari arsip...');

        $hasil = $this->berkas->dariZip($arsip, $antrian);

        $this->ringkasan['berkas_disalin'] = $hasil['disalin'];
        $this->ringkasan['berkas_sudah_ada'] = $hasil['dilewati'];
        $this->ringkasan['berkas_gagal'] = count($hasil['gagal']);

        if ($hasil['gagal'] !== []) {
            $this->lapor->__invoke('Berkas tidak ditemukan di arsip: '.implode(', ', array_slice($hasil['gagal'], 0, 5)).'...');
        }
    }

    /**
     * Nama entri arsip yang mungkin memuat sebuah dokumen. Sebagian berkas tercatat
     * pada direktori tanpa akhiran tetapi terarsip di direktori kembarannya yang
     * berakhiran "1" (sisa penataan ulang berkas di aplikasi lama), jadi keduanya
     * dicoba.
     *
     * @return array<int, string>
     */
    private function kandidatEntri(string $path): array
    {
        $direktori = Str::before($path, '/');
        $sisa = Str::after($path, '/');

        return [
            'public/'.$path,
            'public/'.$direktori.'1/'.$sisa,
            'public/'.rtrim($direktori, '1').'/'.$sisa,
        ];
    }

    /**
     * Jadwal pencairan (gelombang penyerahan anggaran). Tahun kerjanya ditentukan
     * dari tanggal pencairan; bila di luar rentang tahun mana pun, dipakai tahun
     * kerja terakhir yang dipindahkan.
     */
    private function imporJadwalPencairan(): void
    {
        $tahunKerjas = TahunKerja::query()
            ->whereIn('id', array_values($this->petaTahunKerja))
            ->orderBy('tahun')
            ->get();

        foreach ($this->lama->table('distribution_schedules')->orderBy('id')->get() as $lama) {
            $tanggal = $lama->distribution_date_start;

            $tahunKerja = $tahunKerjas->first(
                fn (TahunKerja $tahun): bool => $tahun->start_datetime !== null
                    && $tahun->end_datetime !== null
                    && $tanggal >= $tahun->start_datetime->toDateTimeString()
                    && $tanggal <= $tahun->end_datetime->toDateTimeString(),
            ) ?? $tahunKerjas->last();

            $sudahCair = $lama->status === 'anggaran sudah didistribusikan';

            $jadwal = JadwalPencairan::firstOrNew(['tahun_kerja_id' => $tahunKerja->id, 'name' => $lama->name]);

            if (! $jadwal->exists) {
                $jadwal->fill([
                    'tanggal_pencairan' => $tanggal,
                    'status' => $sudahCair ? EnumStatusPencairan::Dicairkan : EnumStatusPencairan::Dijadwalkan,
                    'catatan' => $lama->description,
                    'dicairkan_at' => $sudahCair ? $lama->updated_at : null,
                ]);
                $jadwal->created_at = $lama->created_at;
                $jadwal->updated_at = $lama->updated_at;
                $jadwal->save();
            }

            $this->petaJadwal[(int) $lama->id] = $jadwal->id;
        }

        $this->ringkasan['jadwal_pencairan'] = count($this->petaJadwal);
    }

    /**
     * Pengajuan anggaran program kerja beserta riwayat verifikasinya. Kosakata status
     * kedua aplikasi kebetulan sama persis sehingga dipakai apa adanya.
     */
    private function imporPengajuan(): void
    {
        $pengaju = $this->lama->table('submission_submitted_bies')
            ->orderBy('id')
            ->get()
            ->keyBy('budget_submission_id');

        $tanggapan = collect($this->lama->table('submission_status_quotes')->orderBy('id')->get())
            ->groupBy('budget_submission_id');

        $logs = collect($this->lama->table('submission_logs')->orderBy('id')->get())
            ->groupBy('budget_submission_id');

        $dibuat = 0;
        $dilewati = 0;

        foreach ($this->lama->table('budget_submissions')->orderBy('id')->get() as $lama) {
            $penawaranId = $this->petaPenawaran[(int) $lama->work_program_offer_id] ?? null;

            if ($penawaranId === null) {
                $dilewati++;

                continue;
            }

            $terakhir = ($tanggapan[$lama->id] ?? collect())->last();
            $status = EnumStatusPengajuan::tryFrom($lama->status) ?? EnumStatusPengajuan::Draft;

            $pengajuan = PengajuanProgramKerja::firstOrNew([
                'penawaran_program_kerja_id' => $penawaranId,
                'unit_kerja_id' => (int) $lama->departement_id,
                'created_at' => $lama->created_at,
            ]);

            if ($pengajuan->exists) {
                $this->petaPengajuan[(int) $lama->id] = $pengajuan->id;
                $dilewati++;

                continue;
            }

            $pengajuan->fill([
                'user_id' => $this->penggunaBaru($pengaju[$lama->id]->user_id ?? null),
                'alokasi_anggaran' => $lama->budget,
                'deskripsi_kegiatan' => $lama->description,
                'status' => $status,
                'catatan_verifikasi' => $terakhir?->comment,
                'diverifikasi_at' => $status === EnumStatusPengajuan::Draft ? null : $terakhir?->created_at,
                'verifikator_id' => $this->penggunaBaru($terakhir?->user_id),
            ]);
            $pengajuan->created_at = $lama->created_at;
            $pengajuan->updated_at = $lama->updated_at;
            $pengajuan->save();

            $this->petaPengajuan[(int) $lama->id] = $pengajuan->id;
            $this->tulisLogPengajuan($pengajuan, $logs[$lama->id] ?? collect(), $tanggapan[$lama->id] ?? collect());
            $dibuat++;
        }

        $this->ringkasan['pengajuan_dibuat'] = $dibuat;
        $this->ringkasan['pengajuan_dilewati'] = $dilewati;
    }

    /**
     * Riwayat pengajuan: catatan naratif aplikasi lama dipertahankan kalimatnya,
     * sedangkan statusnya ditebak dari kata kerja pembuka log.
     *
     * @param  Collection<int, object>  $logs
     * @param  Collection<int, object>  $tanggapan
     */
    private function tulisLogPengajuan(PengajuanProgramKerja $pengajuan, Collection $logs, Collection $tanggapan): void
    {
        $baris = $logs->map(fn (object $log): array => [
            'user_id' => $this->penggunaBaru($log->user_id),
            'status' => $this->statusLogPengajuan($log->log),
            'description' => Str::ucfirst($log->log).'.',
            'properties' => ['sumber' => 'LAMADU', 'kode' => $log->reference_id],
            'created_at' => $log->created_at,
            'updated_at' => $log->updated_at,
        ]);

        $baris = $baris->merge($tanggapan
            ->filter(fn (object $quote): bool => filled($quote->comment))
            ->map(fn (object $quote): array => [
                'user_id' => $this->penggunaBaru($quote->user_id),
                'status' => EnumStatusPengajuan::tryFrom($quote->status)?->value,
                'description' => 'Catatan verifikasi: '.strip_tags((string) $quote->comment),
                'properties' => ['sumber' => 'LAMADU', 'catatan' => strip_tags((string) $quote->comment)],
                'created_at' => $quote->created_at,
                'updated_at' => $quote->updated_at,
            ]));

        if ($baris->isEmpty()) {
            return;
        }

        $pengajuan->logs()->createMany(
            $this->lengkapiStatusLog($baris, EnumStatusPengajuan::Draft->value),
        );
    }

    /**
     * Peristiwa yang tidak menyiratkan status tertentu (mis. mengunggah dokumen)
     * mewarisi status peristiwa sebelumnya, sehingga riwayat terbaca sebagai satu
     * alur yang maju dan bukan lompatan ke status akhir.
     *
     * @param  Collection<int, array<string, mixed>>  $baris
     * @return array<int, array<string, mixed>>
     */
    private function lengkapiStatusLog(Collection $baris, string $statusAwal): array
    {
        $terakhir = $statusAwal;

        return $baris
            ->sortBy('created_at')
            ->map(function (array $log) use (&$terakhir): array {
                $log['status'] ??= $terakhir;
                $terakhir = $log['status'];

                return $log;
            })
            ->values()
            ->all();
    }

    private function statusLogPengajuan(string $log): ?string
    {
        return match (true) {
            str_starts_with($log, 'membuat pengajuan'), str_starts_with($log, 'merubah pengajuan') => EnumStatusPengajuan::Draft->value,
            str_starts_with($log, 'mengirim pengajuan') => EnumStatusPengajuan::Diajukan->value,
            str_starts_with($log, 'telah menerima') => EnumStatusPengajuan::Diterima->value,
            str_starts_with($log, 'telah menolak') => EnumStatusPengajuan::Ditolak->value,
            str_starts_with($log, 'telah meresponse') => EnumStatusPengajuan::Revisi->value,
            default => null,
        };
    }

    /**
     * Realisasi program kerja: inti impor. Satu baris `program_realizations` menjadi
     * satu realisasi lengkap dengan hasil verifikasi berjenjang, pencairan anggaran,
     * laporan pelaksanaan, dan dokumen-dokumennya.
     */
    private function imporRealisasi(): void
    {
        // Satu realisasi bisa punya lebih dari satu laporan bila unit kerja mengirim
        // ulang; isi laporan terakhirlah yang berlaku, tetapi berkas dari seluruh
        // laporan tetap dikumpulkan.
        $semuaLaporan = collect($this->lama->table('realization_reports')->orderBy('id')->get());
        $laporan = $semuaLaporan->keyBy('program_realization_id');
        $realisasiLaporan = $semuaLaporan->pluck('program_realization_id', 'id');

        $tanggapan = collect($this->lama->table('realization_status_quotes')->orderBy('id')->get())
            ->groupBy('program_realization_id');

        $logs = collect($this->lama->table('realization_logs')->orderBy('id')->get())
            ->groupBy('program_realization_id');

        $pengaju = $this->lama->table('realization_submited_bies')->orderBy('id')->get()->keyBy('program_realization_id');

        $penetapNominal = collect($this->lama->table('budget_realization_approved_bies')->orderBy('id')->get())
            ->keyBy('program_realization_id');

        $pencairan = collect($this->lama->table('budget_distribution_schedules')->orderBy('id')->get())
            ->whereNotNull('program_realization_id')
            ->keyBy('program_realization_id');

        $dokumenProposal = collect($this->lama->table('realization_documents')->orderBy('id')->get())
            ->groupBy('program_realization_id');

        $dokumenLaporan = collect($this->lama->table('realization_report_documents')->orderBy('id')->get())
            ->groupBy(fn (object $berkas): int => (int) ($realisasiLaporan[$berkas->realization_report_id] ?? 0));

        $dibuat = 0;
        $dilewati = 0;

        foreach ($this->lama->table('program_realizations')->orderBy('id')->get() as $lama) {
            $pengajuanId = $this->petaPengajuan[(int) $lama->budget_submission_id] ?? null;

            if ($pengajuanId === null) {
                $dilewati++;

                continue;
            }

            $realisasi = RealisasiProgramKerja::firstOrNew([
                'pengajuan_program_kerja_id' => $pengajuanId,
                'created_at' => $lama->created_at,
            ]);

            if ($realisasi->exists) {
                $dilewati++;

                continue;
            }

            $laporanLama = $laporan[$lama->id] ?? null;
            $berkasProposal = $dokumenProposal[$lama->id] ?? collect();
            $berkasLaporan = $dokumenLaporan[$lama->id] ?? collect();
            $quotes = $tanggapan[$lama->id] ?? collect();
            $atribut = [
                'name' => $lama->title,
                'description' => $lama->description,
                'start_datetime' => $lama->start_date,
                'end_datetime' => $lama->end_date,
                'status' => $this->statusRealisasi($lama->status),
                'catatan_verifikasi' => $quotes->last()?->comment,
                ...$this->atributVerifikasi($quotes),
                ...$this->atributNominal($lama, $laporanLama, $penetapNominal[$lama->id] ?? null),
                ...$this->atributPencairan($pencairan[$lama->id] ?? null),
                ...$this->atributLaporan($lama, $laporanLama, $quotes),
                ...$this->atributDokumen($berkasProposal, $berkasLaporan),
            ];

            $realisasi->fill($atribut);
            $realisasi->created_at = $lama->created_at;
            $realisasi->updated_at = $lama->updated_at;
            $realisasi->save();

            $this->rapikanDokumen($realisasi, $berkasProposal, $berkasLaporan);
            $this->tulisLogRealisasi($realisasi, $logs[$lama->id] ?? collect(), $quotes, $pengaju[$lama->id] ?? null);
            $this->tulisLogTautan($realisasi, $berkasProposal->merge($berkasLaporan));
            $dibuat++;
        }

        $this->ringkasan['realisasi_dibuat'] = $dibuat;
        $this->ringkasan['realisasi_dilewati'] = $dilewati;
    }

    private function statusRealisasi(string $status): EnumStatusRealisasi
    {
        return match (true) {
            $status === 'draft' => EnumStatusRealisasi::Draft,
            $status === 'realisasi selesai' => EnumStatusRealisasi::Selesai,
            $status === 'menunggu laporan realisasi' => EnumStatusRealisasi::MenungguLaporan,
            str_starts_with($status, 'proses validasi laporan') => EnumStatusRealisasi::VerifikasiLaporan,
            str_starts_with($status, 'permintaan ditolak') => EnumStatusRealisasi::Ditolak,
            str_contains($status, 'wakil rektor') => EnumStatusRealisasi::VerifikasiWakil,
            str_contains($status, 'rektor') => EnumStatusRealisasi::VerifikasiRektor,
            str_contains($status, 'keuangan') => EnumStatusRealisasi::VerifikasiKeuangan,
            default => EnumStatusRealisasi::Diajukan,
        };
    }

    /**
     * Siapa menyetujui apa: peran tiap penanggap disimpulkan dari role yang
     * dipegangnya di aplikasi lama, karena data lama hanya menyimpan status tanpa
     * menyebut tahap verifikasinya.
     *
     * @param  Collection<int, object>  $quotes
     * @return array<string, mixed>
     */
    private function atributVerifikasi(Collection $quotes): array
    {
        $diterima = $quotes->where('status', 'diterima');

        $rektor = $diterima->first(fn (object $quote): bool => $this->berperan((int) $quote->user_id, 'rektorat', 'super-admin'));
        $wakil = $diterima->first(fn (object $quote): bool => $this->berperan((int) $quote->user_id, 'wakil-rektor-1', 'wakil-rektor-2', 'wakil-rektor-3'));
        $keuangan = $quotes->first(fn (object $quote): bool => $this->berperan((int) $quote->user_id, 'keuangan'));

        return [
            'disetujui_rektor_at' => $rektor?->created_at,
            'rektor_id' => $this->penggunaBaru($rektor?->user_id),
            'disetujui_wakil_at' => $wakil?->created_at,
            'wakil_id' => $this->penggunaBaru($wakil?->user_id),
            'keuangan_id' => $this->penggunaBaru($keuangan?->user_id),
        ];
    }

    /**
     * Nominal yang diajukan, disetujui, dan akhirnya terpakai. Anggaran yang digunakan
     * dihitung mundur dari laporan: sisa mengurangi, kekurangan menambah nominal yang
     * diterima unit kerja.
     *
     * @return array<string, mixed>
     */
    private function atributNominal(object $lama, ?object $laporan, ?object $penetap): array
    {
        $diajukan = (float) $lama->budget;
        $disetujui = (float) $lama->budget_approved;
        $diterima = $disetujui > 0 ? $disetujui : $diajukan;

        $statusAnggaran = $this->statusAnggaran($laporan);
        $selisih = (float) (($laporan?->remaining_budget ?? 0) + ($laporan?->budget_deficit ?? 0));

        $digunakan = match ($statusAnggaran) {
            EnumStatusAnggaran::Sisa => max(0.0, $diterima - $selisih),
            EnumStatusAnggaran::Kurang => $diterima + $selisih,
            EnumStatusAnggaran::Habis => $diterima,
            default => $diajukan,
        };

        return [
            'nominal_diajukan' => $diajukan,
            'nominal_disetujui' => $disetujui > 0 ? $disetujui : null,
            'penentu_nominal_id' => $this->penggunaBaru($penetap?->user_id),
            'anggaran_digunakan' => $digunakan,
            'status_anggaran' => $statusAnggaran,
            'nominal_selisih_anggaran' => $statusAnggaran?->memerlukanSelisih() ? $selisih : null,
            // Data lama tidak mencatat tindak lanjut selisih anggaran, jadi selisih yang
            // pernah dilaporkan ditandai masih menunggu Biro Keuangan.
            'status_penyelesaian_anggaran' => $statusAnggaran?->memerlukanSelisih() && $selisih > 0
                ? EnumStatusPenyelesaianAnggaran::Menunggu
                : null,
        ];
    }

    private function statusAnggaran(?object $laporan): ?EnumStatusAnggaran
    {
        return match ($laporan?->budget_realization_status) {
            'Anggaran tergunakan semua' => EnumStatusAnggaran::Habis,
            'Terdapat sisa anggaran' => EnumStatusAnggaran::Sisa,
            'Kekurangan anggaran' => EnumStatusAnggaran::Kurang,
            default => null,
        };
    }

    /**
     * Penjadwalan dan penyerahan anggaran.
     *
     * @return array<string, mixed>
     */
    private function atributPencairan(?object $pencairan): array
    {
        if ($pencairan === null) {
            return [];
        }

        $sudahDiterima = $pencairan->status === 'anggaran sudah diterima';

        return [
            'jadwal_pencairan_id' => $this->petaJadwal[(int) $pencairan->distribution_schedule_id] ?? null,
            'status_pencairan' => $sudahDiterima ? EnumStatusPencairan::Dicairkan : EnumStatusPencairan::Dijadwalkan,
            'dicairkan_at' => $sudahDiterima ? ($pencairan->taken_date ?? $pencairan->updated_at) : null,
            'metode_pembayaran' => match ($pencairan->payment_mode) {
                'transfer' => EnumMetodePembayaran::Transfer,
                'cash' => EnumMetodePembayaran::Tunai,
                default => null,
            },
            'rekening_bank_id' => $pencairan->payment_mode === 'transfer'
                ? ($this->petaRekening[(int) $pencairan->rekening_id] ?? null)
                : null,
        ];
    }

    /**
     * Laporan pelaksanaan beserta verifikasinya.
     *
     * @param  Collection<int, object>  $quotes
     * @return array<string, mixed>
     */
    private function atributLaporan(object $lama, ?object $laporan, Collection $quotes): array
    {
        if ($laporan === null) {
            return ['persentase_ketercapaian' => (int) $lama->fulfillment ?: null];
        }

        $disetujui = $quotes->where('status', 'laporan diterima')->last();

        return [
            'evaluasi_pengerjaan' => $laporan->message,
            'persentase_ketercapaian' => (int) ($laporan->progress_percentage ?: $lama->fulfillment) ?: null,
            'laporan_diserahkan_at' => $laporan->created_at,
            'laporan_disetujui_at' => $disetujui?->created_at,
            'verifikator_laporan_id' => $this->penggunaBaru($disetujui?->user_id),
        ];
    }

    /**
     * Kolom berkas realisasi. Dokumen bertipe tautan (mis. Google Drive) tidak punya
     * padanan kolom di sistem ini sehingga tidak ikut ke sini; tautannya tetap
     * tersimpan sebagai catatan riwayat lewat {@see self::tulisLogRealisasi()}.
     *
     * @param  Collection<int, object>  $proposal
     * @param  Collection<int, object>  $laporan
     * @return array<string, mixed>
     */
    private function atributDokumen(Collection $proposal, Collection $laporan): array
    {
        return [
            'proposal_path' => $this->pathDokumen($proposal),
            'proposal_original_names' => $this->namaDokumen($proposal),
            'laporan_path' => $this->pathDokumen($laporan),
            'laporan_original_names' => $this->namaDokumen($laporan),
        ];
    }

    /**
     * @param  Collection<int, object>  $dokumen
     * @return array<int, string>|null
     */
    private function pathDokumen(Collection $dokumen): ?array
    {
        $paths = $dokumen
            ->where('type', 'file')
            ->map(fn (object $berkas): ?string => $this->petaBerkas[$berkas->path] ?? null)
            ->filter()
            ->values()
            ->all();

        return $paths !== [] ? $paths : null;
    }

    /**
     * @param  Collection<int, object>  $dokumen
     * @return array<string, string>|null
     */
    private function namaDokumen(Collection $dokumen): ?array
    {
        $nama = $dokumen
            ->where('type', 'file')
            ->mapWithKeys(function (object $berkas): array {
                $path = $this->petaBerkas[$berkas->path] ?? null;

                return $path !== null ? [$path => $berkas->name] : [];
            })
            ->all();

        return $nama !== [] ? $nama : null;
    }

    /**
     * Menyelaraskan metadata dokumen yang dibentuk otomatis oleh model realisasi
     * dengan data aslinya: ukuran dan waktu unggah aplikasi lama, bukan waktu impor.
     *
     * @param  Collection<int, object>  $proposal
     * @param  Collection<int, object>  $laporan
     */
    private function rapikanDokumen(RealisasiProgramKerja $realisasi, Collection $proposal, Collection $laporan): void
    {
        $berkas = $proposal->merge($laporan)->where('type', 'file');

        foreach ($berkas as $dokumen) {
            $path = $this->petaBerkas[$dokumen->path] ?? null;

            if ($path === null) {
                continue;
            }

            RealisasiDokumen::query()
                ->where('realisasi_program_kerja_id', $realisasi->id)
                ->where('path', $path)
                ->update([
                    'size' => $dokumen->size,
                    'uploaded_at' => $dokumen->created_at,
                    'created_at' => $dokumen->created_at,
                    'updated_at' => $dokumen->updated_at,
                ]);
        }
    }

    /**
     * Riwayat realisasi disusun dari dua sumber: catatan naratif aplikasi lama (siapa
     * mengunggah/mengubah apa) dan tanggapan berstatus (siapa menyetujui pada tahap
     * mana). Keduanya digabung lalu diurutkan menurut waktu kejadian.
     *
     * @param  Collection<int, object>  $logs
     * @param  Collection<int, object>  $quotes
     */
    private function tulisLogRealisasi(RealisasiProgramKerja $realisasi, Collection $logs, Collection $quotes, ?object $pengaju): void
    {
        $baris = $logs->map(fn (object $log): array => [
            'user_id' => $this->penggunaBaru($log->user_id),
            'status' => $this->statusLogRealisasi($log->log),
            'description' => Str::ucfirst($log->log).'.',
            'properties' => ['sumber' => 'LAMADU', 'kode' => $log->reference_id],
            'created_at' => $log->created_at,
            'updated_at' => $log->updated_at,
        ]);

        $baris = $baris->merge($quotes->map(fn (object $quote): array => [
            'user_id' => $this->penggunaBaru($quote->user_id),
            'status' => $this->statusTanggapan($quote)?->value,
            'description' => $this->kalimatTanggapan($quote),
            'properties' => [
                'sumber' => 'LAMADU',
                'status_lama' => $quote->status,
                'catatan' => filled($quote->comment) ? strip_tags((string) $quote->comment) : null,
            ],
            'created_at' => $quote->created_at,
            'updated_at' => $quote->updated_at,
        ]));

        if ($pengaju !== null) {
            $baris = $baris->push([
                'user_id' => $this->penggunaBaru($pengaju->user_id),
                'status' => EnumStatusRealisasi::Diajukan->value,
                'description' => 'Realisasi diajukan untuk verifikasi.',
                'properties' => ['sumber' => 'LAMADU'],
                'created_at' => $pengaju->created_at,
                'updated_at' => $pengaju->updated_at,
            ]);
        }

        if ($baris->isEmpty()) {
            return;
        }

        $realisasi->logs()->createMany(
            $this->lengkapiStatusLog($baris, EnumStatusRealisasi::Draft->value),
        );
    }

    /**
     * Dokumen aplikasi lama yang berupa tautan (mis. Google Drive) tidak bisa disalin
     * menjadi berkas, jadi alamatnya diselamatkan sebagai catatan riwayat agar tetap
     * bisa ditelusuri.
     *
     * @param  Collection<int, object>  $dokumen
     */
    private function tulisLogTautan(RealisasiProgramKerja $realisasi, Collection $dokumen): void
    {
        $tautan = $dokumen->where('type', 'link');

        if ($tautan->isEmpty()) {
            return;
        }

        $realisasi->logs()->createMany($tautan->map(fn (object $berkas): array => [
            'user_id' => null,
            'status' => $realisasi->status->value,
            'description' => 'Dokumen "'.$berkas->name.'" pada aplikasi lama berupa tautan: '.$berkas->path,
            'properties' => ['sumber' => 'LAMADU', 'tautan' => $berkas->path, 'nama' => $berkas->name],
            'created_at' => $berkas->created_at,
            'updated_at' => $berkas->updated_at,
        ])->values()->all());
    }

    private function statusLogRealisasi(string $log): ?string
    {
        return match (true) {
            str_starts_with($log, 'membuat realisasi') => EnumStatusRealisasi::Draft->value,
            str_starts_with($log, 'mengajukan laporan') => EnumStatusRealisasi::VerifikasiLaporan->value,
            default => null,
        };
    }

    /**
     * Status baru yang diwakili sebuah tanggapan. Persetujuan ("diterima") meneruskan
     * berkas ke tahap berikutnya, sehingga statusnya ditentukan oleh peran penanggap.
     */
    private function statusTanggapan(object $quote): ?EnumStatusRealisasi
    {
        $userId = (int) $quote->user_id;

        return match ($quote->status) {
            'diterima' => match (true) {
                $this->berperan($userId, 'rektorat') => EnumStatusRealisasi::VerifikasiWakil,
                $this->berperan($userId, 'wakil-rektor-1', 'wakil-rektor-2', 'wakil-rektor-3') => EnumStatusRealisasi::VerifikasiKeuangan,
                $this->berperan($userId, 'keuangan') => EnumStatusRealisasi::Dijadwalkan,
                default => EnumStatusRealisasi::VerifikasiRektor,
            },
            'dalam antrian' => EnumStatusRealisasi::Dijadwalkan,
            'antrian dibatalkan' => EnumStatusRealisasi::VerifikasiKeuangan,
            'anggaran sudah diterima' => EnumStatusRealisasi::MenungguLaporan,
            'proses validasi laporan' => EnumStatusRealisasi::VerifikasiLaporan,
            'laporan diterima' => EnumStatusRealisasi::Selesai,
            'revisi', 'revisi laporan' => EnumStatusRealisasi::Revisi,
            'ditolak' => EnumStatusRealisasi::Ditolak,
            default => null,
        };
    }

    private function kalimatTanggapan(object $quote): string
    {
        $kalimat = match ($quote->status) {
            'diterima' => 'Realisasi disetujui pada tahap verifikasi.',
            'dalam antrian' => 'Realisasi masuk antrian pencairan anggaran.',
            'antrian dibatalkan' => 'Realisasi dikeluarkan dari antrian pencairan.',
            'anggaran sudah diterima' => 'Anggaran realisasi sudah diserahkan ke unit kerja.',
            'proses validasi laporan' => 'Laporan realisasi dikirim untuk divalidasi.',
            'laporan diterima' => 'Laporan realisasi disetujui dan realisasi dinyatakan selesai.',
            'revisi', 'revisi laporan' => 'Realisasi dikembalikan untuk diperbaiki.',
            'ditolak' => 'Realisasi ditolak.',
            default => 'Status realisasi diperbarui menjadi "'.$quote->status.'".',
        };

        return filled($quote->comment)
            ? $kalimat.' Catatan: '.strip_tags((string) $quote->comment)
            : $kalimat;
    }

    /**
     * Pemasukan yang dilaporkan menyertai realisasi. Data lama hanya mencatat besaran,
     * lokasi penyimpanan, dan apakah dananya sudah masuk; sisanya diisi seperlunya.
     */
    private function imporPemasukan(): void
    {
        $realisasiLama = $this->lama->table('program_realizations')->get()->keyBy('id');
        $dibuat = 0;

        $laporan = $this->lama->table('realization_reports')
            ->where('has_income', 'Ada')
            ->where('income_amount', '>', 0)
            ->orderBy('id')
            ->get();

        foreach ($laporan as $lama) {
            $pengajuanId = $this->petaPengajuan[(int) $lama->budget_submission_id] ?? null;
            $realisasiLamaId = (int) $lama->program_realization_id;
            $sumberRealisasi = $realisasiLama[$realisasiLamaId] ?? null;

            if ($pengajuanId === null || $sumberRealisasi === null) {
                continue;
            }

            $realisasi = RealisasiProgramKerja::query()
                ->where('pengajuan_program_kerja_id', $pengajuanId)
                ->where('created_at', $sumberRealisasi->created_at)
                ->first();

            if ($realisasi === null) {
                continue;
            }

            $sudahMasuk = $lama->income_status === 'Sudah';

            $pemasukan = Pemasukan::firstOrNew([
                'realisasi_program_kerja_id' => $realisasi->id,
                'nominal_pendapatan' => $lama->income_amount,
            ]);

            if ($pemasukan->exists) {
                continue;
            }

            $pemasukan->fill([
                'unit_kerja_id' => (int) $lama->departement_id,
                'user_id' => $realisasi->pengajuanProgramKerja?->user_id,
                'sumber' => EnumSumberPemasukan::Realisasi,
                'pengajuan_program_kerja_id' => $pengajuanId,
                'rincian_kegiatan' => $sumberRealisasi->title,
                'jenis_waktu' => EnumJenisWaktuPemasukan::SatuHari,
                'tanggal_pelaksanaan' => $sumberRealisasi->start_date,
                'keterangan' => trim(strip_tags((string) $lama->income_description).' (Disimpan di: '.($lama->income_location ?? 'tidak dicatat').')'),
                'status' => $sudahMasuk ? EnumStatusPemasukan::Valid : EnumStatusPemasukan::Diajukan,
                'divalidasi_at' => $sudahMasuk ? $lama->updated_at : null,
            ]);
            $pemasukan->created_at = $lama->created_at;
            $pemasukan->updated_at = $lama->updated_at;
            $pemasukan->save();

            $dibuat++;
        }

        $this->ringkasan['pemasukan_dibuat'] = $dibuat;
    }

    private function penggunaBaru(int|string|null $userIdLama): ?int
    {
        return $userIdLama === null ? null : ($this->petaPengguna[(int) $userIdLama] ?? null);
    }
}
