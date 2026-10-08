<?php

namespace App\Services\ImporDataLama;

use App\Enums\EnumRole;
use App\Enums\EnumStatusAnggaran;
use App\Enums\EnumStatusPencairan;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusPenyelesaianAnggaran;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Models\AcuanProgramKerja;
use App\Models\Kategori;
use App\Models\KelompokAcuan;
use App\Models\PaguAnggaran;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\RealisasiDokumen;
use App\Models\RealisasiProgramKerja;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Memindahkan data tahun kerja 2023 dari aplikasi kinerja generasi pertama (basis
 * data `kinerja_2023`).
 *
 * Aplikasi itu jauh berbeda dari sistem ini: program kerjanya berdiri sendiri per
 * tahun (bukan acuan lintas periode), statusnya berupa angka `steps`, dan tidak ada
 * catatan siapa mengerjakan apa. Karena itu impor 2023 membentuk kelompok acuannya
 * sendiri — "Program Kerja 2023 (Aplikasi Lama)" — agar daftar acuan periode berjalan
 * tidak tercampur program kerja lama yang sudah tidak dipakai.
 *
 * Unit kerja dicocokkan lewat tabel padanan {@see self::PADANAN_UNIT_KERJA} karena
 * aplikasi lama memakai singkatan (mis. "BAAK") sedangkan sistem ini memakai nama
 * lengkap.
 */
class ImporLama2023
{
    public const KONEKSI = 'lama_2023';

    public const DIREKTORI_BERKAS = 'db/kinerja2023';

    public const TAHUN = 2023;

    /**
     * Slug unit kerja aplikasi 2023 → slug unit kerja sistem ini. Unit yang tidak
     * disebut di sini (mis. "Medical Center") tidak punya padanan; datanya dilewati
     * dan dilaporkan pada ringkasan.
     */
    private const PADANAN_UNIT_KERJA = [
        'baak' => 'biro-administrasi-akademik-dan-kemahasiswaan-baak',
        'bak' => 'biro-administrasi-keuangan-bak',
        'bau' => 'biro-administrasi-umum-bau',
        'bp3s' => 'biro-perencanaan-pembangunan-dan-pemeliharaan-sarana-prasarana-bp3s',
        'feb' => 'fakultas-ekonomi-dan-bisnis-feb',
        'fikes' => 'fakultas-ilmu-kesehatan-fik',
        'fstp' => 'fakultas-sains-teknologi-dan-pendidikan-fstp',
        'halal-center' => 'halal-center',
        'kui' => 'kantor-urusan-internasional-kui',
        'labaik' => 'lembaga-pengembangan-al-islam-dan-kemuhammadiyahan-labaik',
        'lembaga-kemahasiswaan' => 'lembaga-kemahasiswaan',
        'lembaga-kemakmuran-masjid' => 'lembaga-kemakmuran-masjid',
        'lembaga-sertifikasi' => 'lembaga-sertifikasi',
        'lesikom' => 'lembaga-informasi-dan-komunikasi-lesikom',
        'lpm' => 'lembaga-penjaminan-mutu-lpm',
        'lppm' => 'lembaga-penelitian-dan-pengabdian-kepada-masyarakat-lppm',
        'perspustakaan' => 'perpustakaan',
        'pusat-bahasa' => 'pusat-bahasa',
        'pusat-bisnis' => 'pusat-bisnis',
        'pusat-hki-publikasi-ilmiah' => 'pusat-hak-kekayaan-intelektual-dan-publikasi-ilmiah',
        'pusat-it' => 'pusat-teknologi-informasi-pti',
        'pusat-laboratorium' => 'pusat-laboratorium',
        'pusat-layanan-bimbingan-konseling' => 'pusat-layanan-bimbingan-konseling',
        'pusat-pengembangan-jurnal' => 'pusat-pengembangan-jurnal',
        'pusat-pengembangan-pembelajaran' => 'pusat-pengembangan-pembelajaran-dan-rpl',
        'pusat-tracer-study' => 'pusat-tracer-study',
        'rektor' => 'rektorat',
        'sdi' => 'sumber-daya-insan-sdi',
        'sekretariat-rektor' => 'sekretariat-rektor',
        'spi' => 'satuan-pengawas-internal-spi',
        'wakil-rektor' => 'wakil-rektor-2',
    ];

    private ConnectionInterface $lama;

    private TahunKerja $tahunKerja;

    /** @var array<int, int> id unit kerja lama → id unit kerja baru */
    private array $petaUnitKerja = [];

    /** @var array<int, int> id program_kerja lama → id penawaran baru */
    private array $petaPenawaran = [];

    /** @var array<int, int> id user lama → id user baru */
    private array $petaPengguna = [];

    /** @var array<int, int> id pengajuan lama → id pengajuan baru */
    private array $petaPengajuan = [];

    /** @var array<string, string> path berkas lama → path pada disk aplikasi */
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
     * @param  Closure(string): void|null  $lapor
     * @return array<string, int>
     */
    public function jalankan(bool $salinBerkas = true, ?string $direktori = null, ?Closure $lapor = null): array
    {
        $this->lapor = $lapor ?? static function (string $pesan): void {};
        $this->lama = DB::connection(self::KONEKSI);

        $this->siapkanUnitKerja();
        $this->siapkanTahunKerja();
        $this->siapkanPengguna();
        $this->siapkanPenawaran();
        $this->imporPagu();

        if ($salinBerkas) {
            $this->salinBerkas($direktori ?? base_path(self::DIREKTORI_BERKAS));
        }

        DB::transaction(function (): void {
            $this->imporPengajuan();
            $this->imporRealisasi();
        });

        return $this->ringkasan;
    }

    private function siapkanUnitKerja(): void
    {
        $unitKerjas = UnitKerja::pluck('id', 'slug');
        $tanpaPadanan = [];

        foreach ($this->lama->table('unit_kerjas')->get() as $lama) {
            $slugBaru = self::PADANAN_UNIT_KERJA[$lama->slug] ?? null;
            $id = $slugBaru !== null ? $unitKerjas[$slugBaru] ?? null : null;

            if ($id === null) {
                $tanpaPadanan[] = $lama->name;

                continue;
            }

            $this->petaUnitKerja[(int) $lama->id] = (int) $id;
        }

        $this->ringkasan['unit_kerja_tertaut'] = count($this->petaUnitKerja);
        $this->ringkasan['unit_kerja_tanpa_padanan'] = count($tanpaPadanan);

        if ($tanpaPadanan !== []) {
            $this->lapor->__invoke('Unit kerja tanpa padanan: '.implode(', ', $tanpaPadanan));
        }
    }

    /**
     * Tahun kerja 2023 dibentuk bila belum ada, memakai periode jabatan yang sedang
     * aktif. Statusnya Selesai karena tahun tersebut sudah lewat.
     */
    private function siapkanTahunKerja(): void
    {
        $periode = Periode::query()->where('is_active', true)->first()
            ?? Periode::query()->orderBy('id')->first();

        if ($periode === null) {
            throw new RuntimeException('Periode jabatan belum ada; jalankan seeder master data lebih dulu.');
        }

        $tahunKerja = TahunKerja::firstOrNew(['tahun' => self::TAHUN]);

        if (! $tahunKerja->exists) {
            $tahunKerja->fill([
                'periode_id' => $periode->id,
                'name' => 'Tahun Kerja '.self::TAHUN,
                'status' => EnumStatusTahunKerja::Selesai,
                'description' => 'Tahun anggaran '.self::TAHUN.', dipindahkan dari aplikasi kinerja generasi pertama.',
                'start_datetime' => self::TAHUN.'-09-01 00:00:00',
                'end_datetime' => (self::TAHUN + 1).'-08-31 23:59:59',
            ])->save();
        }

        $this->tahunKerja = $tahunKerja;
    }

    private function siapkanPengguna(): void
    {
        $penggunaLama = collect($this->lama->table('users')->get())->map(fn (object $user): array => [
            'id' => (int) $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'nama' => (string) $user->name,
            'password' => $user->password,
            'aktif' => (bool) $user->status,
            'unit_kerja_id' => $this->petaUnitKerja[(int) $user->unit_kerja_id] ?? null,
            'roles' => [match ($user->role) {
                'admin' => EnumRole::Admin->value,
                'rektor' => EnumRole::Rektor->value,
                'wakil-rektor' => EnumRole::WakilRektorII->value,
                default => EnumRole::UnitKerja->value,
            }],
        ]);

        $this->petaPengguna = $this->pengguna->petakan($penggunaLama);
        $this->ringkasan['pengguna_dibuat'] = $this->pengguna->jumlahDibuat();
    }

    /**
     * Program kerja 2023 dibentuk menjadi acuan pada kelompok acuannya sendiri, lalu
     * langsung ditawarkan pada tahun kerja 2023. Penawaran dibentuk manual (bukan
     * lewat GeneratePenawaranFromAcuan) supaya tiap program kerja lama terpetakan
     * satu-satu ke penawarannya.
     */
    private function siapkanPenawaran(): void
    {
        $kelompokAcuan = KelompokAcuan::firstOrCreate(
            ['name' => 'Program Kerja '.self::TAHUN.' (Aplikasi Lama)'],
            [
                'tahun_mulai' => self::TAHUN,
                'tahun_selesai' => self::TAHUN,
                'description' => 'Daftar program kerja tahun '.self::TAHUN.' dari aplikasi kinerja generasi pertama. Disimpan terpisah dari acuan periode berjalan karena penomoran dan indikatornya berbeda.',
                'is_active' => false,
            ],
        );

        if ($this->tahunKerja->kelompok_acuan_id === null) {
            $this->tahunKerja->update(['kelompok_acuan_id' => $kelompokAcuan->id]);
        }

        $bidangs = $this->petaBidang();
        $kategoriBawaan = $this->kategoriBawaan();
        $kategoriAcuan = $this->kategoriAcuanBerjalan();
        $dilewati = 0;

        foreach ($this->lama->table('program_kerjas')->orderBy('id')->get() as $lama) {
            $unitKerjaId = $this->petaUnitKerja[(int) $lama->unit_kerja_id] ?? null;

            if ($unitKerjaId === null) {
                $dilewati++;

                continue;
            }

            $bidangId = $bidangs[(int) $lama->bidang_id] ?? null;

            if ($bidangId === null) {
                $dilewati++;

                continue;
            }

            $acuan = AcuanProgramKerja::firstOrCreate(
                [
                    'kelompok_acuan_id' => $kelompokAcuan->id,
                    'unit_kerja_id' => $unitKerjaId,
                    'name' => $lama->kegiatan,
                    'indikator' => $lama->indikator,
                ],
                [
                    'bidang_id' => $bidangId,
                    // Aplikasi lama tidak memisahkan IKU/IKT. Kategorinya diwarisi dari acuan
                    // periode berjalan yang kegiatannya sama; bila tak ada padanan, dipakai
                    // kategori yang paling banyak dipakai.
                    'kategori_id' => $kategoriAcuan[$unitKerjaId.'|'.$this->normalkan($lama->kegiatan)] ?? $kategoriBawaan,
                    'program_id' => $this->programId($lama->name),
                    'aktifitas' => $lama->detail_kegiatan,
                    'nilai_standar' => $lama->target,
                    'satuan_nilai_standar' => $lama->satuan,
                    'is_active' => false,
                ],
            );

            $acuan->targets()->firstOrCreate(
                ['tahun' => self::TAHUN],
                ['nilai' => $lama->target, 'satuan' => $lama->satuan],
            );

            $penawaran = PenawaranProgramKerja::firstOrCreate(
                [
                    'acuan_program_kerja_id' => $acuan->id,
                    'tahun_kerja_id' => $this->tahunKerja->id,
                    'unit_kerja_id' => $unitKerjaId,
                ],
                [
                    'name' => $acuan->name,
                    'bidang_id' => $acuan->bidang_id,
                    'kategori_id' => $acuan->kategori_id,
                    'program_id' => $acuan->program_id,
                    'aktifitas' => $acuan->aktifitas,
                    'indikator' => $acuan->indikator,
                    'nilai_standar' => $acuan->nilai_standar,
                    'satuan_nilai_standar' => $acuan->satuan_nilai_standar,
                    'target' => trim($lama->target.' '.$lama->satuan),
                    'is_active' => true,
                ],
            );

            $this->petaPenawaran[(int) $lama->id] = $penawaran->id;
        }

        $this->ringkasan['penawaran_tertaut'] = count($this->petaPenawaran);
        $this->ringkasan['program_kerja_dilewati'] = $dilewati;
    }

    /**
     * Bidang aplikasi lama dicocokkan lewat namanya; hanya "SDM" yang berganti nama
     * menjadi "Sumber Daya Manusia" di sistem ini.
     *
     * @return array<int, int>
     */
    private function petaBidang(): array
    {
        $alias = ['sdm' => 'sumber daya manusia'];
        $bidangs = DB::table('bidangs')->get()->keyBy(fn (object $bidang): string => $this->normalkan($bidang->name));
        $peta = [];

        foreach ($this->lama->table('bidangs')->get() as $lama) {
            $nama = $this->normalkan($lama->name);
            $bidang = $bidangs->get($alias[$nama] ?? $nama);

            if ($bidang !== null) {
                $peta[(int) $lama->id] = (int) $bidang->id;
            }
        }

        return $peta;
    }

    /**
     * Program induk dicocokkan lewat namanya, dan dibentuk bila belum ada.
     */
    private function programId(string $nama): int
    {
        $program = DB::table('programs')->whereRaw('lower(name) = ?', [$this->normalkan($nama)])->first();

        return $program !== null
            ? (int) $program->id
            : Program::create(['name' => trim($nama), 'is_active' => false])->id;
    }

    /**
     * Kategori yang paling banyak dipakai acuan periode berjalan, dipakai sebagai
     * kategori cadangan bagi program kerja lama yang tidak punya padanan.
     */
    private function kategoriBawaan(): int
    {
        $kategoriId = DB::table('acuan_program_kerjas')
            ->select('kategori_id')
            ->groupBy('kategori_id')
            ->orderByRaw('count(*) desc')
            ->value('kategori_id');

        return (int) ($kategoriId ?? Kategori::query()->orderBy('id')->value('id'));
    }

    /**
     * Kategori acuan periode berjalan, berkunci unit kerja + nama kegiatan yang
     * dinormalkan.
     *
     * @return array<string, int>
     */
    private function kategoriAcuanBerjalan(): array
    {
        return DB::table('acuan_program_kerjas')
            ->select(['unit_kerja_id', 'name', 'kategori_id'])
            ->get()
            ->mapWithKeys(fn (object $acuan): array => [
                $acuan->unit_kerja_id.'|'.$this->normalkan($acuan->name) => (int) $acuan->kategori_id,
            ])
            ->all();
    }

    private function normalkan(?string $nilai): string
    {
        return Str::lower(trim((string) preg_replace('/\s+/u', ' ', (string) $nilai)));
    }

    /**
     * Pagu anggaran tiap unit kerja pada tahun 2023.
     */
    private function imporPagu(): void
    {
        $dibuat = 0;

        foreach ($this->lama->table('pagu_anggarans')->get() as $lama) {
            $unitKerjaId = $this->petaUnitKerja[(int) $lama->unit_kerja_id] ?? null;

            if ($unitKerjaId === null) {
                continue;
            }

            $pagu = PaguAnggaran::firstOrCreate(
                ['tahun_kerja_id' => $this->tahunKerja->id, 'unit_kerja_id' => $unitKerjaId],
                ['amount' => $lama->nominal],
            );

            if ($pagu->wasRecentlyCreated) {
                $dibuat++;
            }
        }

        $this->ringkasan['pagu_dibuat'] = $dibuat;
    }

    /**
     * Menyalin proposal dan laporan dari direktori dokumen aplikasi lama. Nama berkas
     * di sana hanya sepuluh karakter sehingga folder acaknya dipakai sebagai awalan
     * agar tidak bertabrakan dengan berkas lain pada direktori datar sistem ini.
     */
    private function salinBerkas(string $direktori): void
    {
        $antrian = [];

        foreach ($this->lama->table('dokumens')->get() as $dokumen) {
            $tujuan = $this->berkas->pathTujuan(
                $dokumen->type === 'laporan' ? SalinBerkasLama::DIREKTORI_LAPORAN : SalinBerkasLama::DIREKTORI_PROPOSAL,
                $dokumen->location,
                awalan: $dokumen->folder,
            );

            $this->petaBerkas[$dokumen->location] = $tujuan;
            // Kolom `location` menyimpan path berawalan "storage/", sedangkan berkasnya
            // diarsipkan tanpa awalan itu.
            $antrian[Str::after($dokumen->location, 'storage/')] = $tujuan;
        }

        $this->lapor->__invoke('Menyalin '.count($antrian).' berkas dokumen 2023...');

        $hasil = $this->berkas->dariDirektori($direktori, $antrian);

        $this->ringkasan['berkas_disalin'] = $hasil['disalin'];
        $this->ringkasan['berkas_sudah_ada'] = $hasil['dilewati'];
        $this->ringkasan['berkas_gagal'] = count($hasil['gagal']);

        if ($hasil['gagal'] !== []) {
            $this->lapor->__invoke('Berkas tidak ada di direktori arsip: '.implode(', ', $hasil['gagal']));
        }
    }

    /**
     * Pengajuan anggaran 2023. Aplikasi lama menandai kemajuan dengan angka `steps`:
     * 0 masih draf, 2 sudah disetujui.
     */
    private function imporPengajuan(): void
    {
        $dibuat = 0;
        $dilewati = 0;

        foreach ($this->lama->table('pengajuans')->orderBy('id')->get() as $lama) {
            $penawaranId = $this->petaPenawaran[(int) $lama->program_kerja_id] ?? null;
            $unitKerjaId = $this->petaUnitKerja[(int) $lama->unit_kerja_id] ?? null;

            if ($penawaranId === null || $unitKerjaId === null) {
                $dilewati++;

                continue;
            }

            $pengajuan = PengajuanProgramKerja::firstOrNew([
                'penawaran_program_kerja_id' => $penawaranId,
                'unit_kerja_id' => $unitKerjaId,
                'created_at' => $lama->created_at,
            ]);

            if ($pengajuan->exists) {
                $this->petaPengajuan[(int) $lama->id] = $pengajuan->id;
                $dilewati++;

                continue;
            }

            $status = (int) $lama->steps >= 2 ? EnumStatusPengajuan::Diterima : EnumStatusPengajuan::Draft;

            $pengajuan->fill([
                'alokasi_anggaran' => $lama->nominal,
                'status' => $status,
                'diverifikasi_at' => $status === EnumStatusPengajuan::Diterima ? $lama->updated_at : null,
            ]);
            $pengajuan->created_at = $lama->created_at;
            $pengajuan->updated_at = $lama->updated_at;
            $pengajuan->save();

            $pengajuan->logs()->create([
                'user_id' => null,
                'status' => $status,
                'description' => 'Pengajuan dipindahkan dari aplikasi kinerja '.self::TAHUN.'.',
                'properties' => ['sumber' => 'kinerja-'.self::TAHUN, 'kode_lama' => $lama->id],
                'created_at' => $lama->created_at,
                'updated_at' => $lama->updated_at,
            ]);

            $this->petaPengajuan[(int) $lama->id] = $pengajuan->id;
            $dibuat++;
        }

        $this->ringkasan['pengajuan_dibuat'] = $dibuat;
        $this->ringkasan['pengajuan_dilewati'] = $dilewati;
    }

    /**
     * Realisasi 2023 beserta rincian anggaran, dokumen, penolakan laporan, dan
     * penyelesaian sisa anggarannya.
     */
    private function imporRealisasi(): void
    {
        $rincian = collect($this->lama->table('realisasi_items')->orderBy('id')->get())
            ->groupBy('realisasi_id');

        $dokumen = collect($this->lama->table('dokumens')->orderBy('id')->get())
            ->groupBy('realisasi_id');

        $penolakan = collect($this->lama->table('pesan_ditolaks')->orderBy('id')->get())
            ->groupBy('realisasi_id');

        $pengembalian = collect($this->lama->table('pengembalians')->orderBy('id')->get())
            ->groupBy('realisasi_id');

        $keterangan = collect($this->lama->table('keterangan_pengembalians')->orderBy('id')->get())
            ->keyBy('realisasi_id');

        $dibuat = 0;
        $dilewati = 0;

        foreach ($this->lama->table('realisasis')->orderBy('id')->get() as $lama) {
            $pengajuanId = $this->petaPengajuan[(int) $lama->pengajuan_id] ?? null;

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

            $berkas = $dokumen[$lama->id] ?? collect();
            $proposal = $berkas->where('type', 'proposal');
            $laporan = $berkas->where('type', 'laporan');
            $status = $this->statusRealisasi((int) $lama->steps, $laporan->isNotEmpty());
            $catatan = ($penolakan[$lama->id] ?? collect())->last();

            $realisasi->fill([
                'name' => $lama->judul,
                'description' => $this->deskripsiDenganRincian($lama->deskripsi, $rincian[$lama->id] ?? collect()),
                'status' => $status,
                'persentase_ketercapaian' => (int) $lama->capaian ?: null,
                'catatan_verifikasi' => $catatan?->keterangan,
                'proposal_path' => $this->pathDokumen($proposal),
                'proposal_original_names' => $this->namaDokumen($proposal),
                'laporan_path' => $this->pathDokumen($laporan),
                'laporan_original_names' => $this->namaDokumen($laporan),
                'laporan_diserahkan_at' => $laporan->first()?->created_at,
                'laporan_disetujui_at' => $status === EnumStatusRealisasi::Selesai ? $lama->updated_at : null,
                ...$this->atributNominal($lama, $pengembalian[$lama->id] ?? collect(), $keterangan[$lama->id] ?? null),
                ...$this->atributPencairan($status),
            ]);
            $realisasi->created_at = $lama->created_at;
            $realisasi->updated_at = $lama->updated_at;
            $realisasi->save();

            $this->rapikanDokumen($realisasi, $berkas);
            $this->tulisLog($realisasi, $lama, $penolakan[$lama->id] ?? collect(), $pengembalian[$lama->id] ?? collect());
            $dibuat++;
        }

        $this->ringkasan['realisasi_dibuat'] = $dibuat;
        $this->ringkasan['realisasi_dilewati'] = $dilewati;
    }

    /**
     * Padanan angka `steps` aplikasi lama pada status realisasi sistem ini.
     */
    private function statusRealisasi(int $steps, bool $adaLaporan): EnumStatusRealisasi
    {
        return match (true) {
            $steps >= 6 => EnumStatusRealisasi::Selesai,
            $steps === 5 => EnumStatusRealisasi::VerifikasiLaporan,
            $steps >= 2 => $adaLaporan ? EnumStatusRealisasi::VerifikasiLaporan : EnumStatusRealisasi::MenungguLaporan,
            default => EnumStatusRealisasi::Draft,
        };
    }

    /**
     * Rincian anggaran aplikasi lama tidak punya tabel padanan di sistem ini, jadi
     * daftarnya ditempelkan pada deskripsi realisasi supaya tidak hilang.
     *
     * @param  Collection<int, object>  $rincian
     */
    private function deskripsiDenganRincian(?string $deskripsi, Collection $rincian): ?string
    {
        if ($rincian->isEmpty()) {
            return $deskripsi;
        }

        $daftar = $rincian->map(fn (object $item): string => sprintf(
            '<li>%s — %d × Rp %s = Rp %s</li>',
            e((string) $item->name),
            (int) $item->quantity,
            number_format((float) $item->nominal_satuan, 0, ',', '.'),
            number_format((float) $item->total, 0, ',', '.'),
        ))->implode('');

        return trim((string) $deskripsi).'<p><strong>Rincian anggaran:</strong></p><ul>'.$daftar.'</ul>';
    }

    /**
     * Nominal beserta penyerapan anggarannya. Keterangan pengembalian aplikasi lama
     * ("plus", "minus", "none") persis menggambarkan status anggaran sistem ini.
     *
     * @param  Collection<int, object>  $pengembalian
     * @return array<string, mixed>
     */
    private function atributNominal(object $lama, Collection $pengembalian, ?object $keterangan): array
    {
        $diajukan = (float) $lama->nominal_keseluruhan;
        $disetujui = (float) $lama->nominal_disetujui;
        $diterima = $disetujui > 0 ? $disetujui : $diajukan;

        $statusAnggaran = match ($keterangan?->keterangan) {
            'plus' => EnumStatusAnggaran::Sisa,
            'minus' => EnumStatusAnggaran::Kurang,
            'none' => EnumStatusAnggaran::Habis,
            default => null,
        };

        $selisih = (float) $pengembalian->sum('nominal');

        $digunakan = match ($statusAnggaran) {
            EnumStatusAnggaran::Sisa => max(0.0, $diterima - $selisih),
            EnumStatusAnggaran::Kurang => $diterima + $selisih,
            default => $diterima,
        };

        return [
            'nominal_diajukan' => $diajukan,
            'nominal_disetujui' => $disetujui > 0 ? $disetujui : null,
            'anggaran_digunakan' => $digunakan,
            'status_anggaran' => $statusAnggaran,
            'nominal_selisih_anggaran' => $statusAnggaran?->memerlukanSelisih() ? $selisih : null,
            'status_penyelesaian_anggaran' => $statusAnggaran?->memerlukanSelisih() && $selisih > 0
                ? EnumStatusPenyelesaianAnggaran::Menunggu
                : null,
        ];
    }

    /**
     * Aplikasi lama tidak menyimpan jadwal pencairan; realisasi yang sudah sampai
     * tahap pelaporan dipastikan anggarannya sudah cair.
     *
     * @return array<string, mixed>
     */
    private function atributPencairan(EnumStatusRealisasi $status): array
    {
        $sudahCair = in_array($status, [
            EnumStatusRealisasi::MenungguLaporan,
            EnumStatusRealisasi::VerifikasiLaporan,
            EnumStatusRealisasi::Selesai,
        ], true);

        return $sudahCair ? ['status_pencairan' => EnumStatusPencairan::Dicairkan] : [];
    }

    /**
     * @param  Collection<int, object>  $dokumen
     * @return array<int, string>|null
     */
    private function pathDokumen(Collection $dokumen): ?array
    {
        $paths = $dokumen
            ->map(fn (object $berkas): ?string => $this->petaBerkas[$berkas->location] ?? null)
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
        $nama = $dokumen->mapWithKeys(function (object $berkas): array {
            $path = $this->petaBerkas[$berkas->location] ?? null;

            return $path !== null ? [$path => $berkas->name] : [];
        })->all();

        return $nama !== [] ? $nama : null;
    }

    /**
     * @param  Collection<int, object>  $dokumen
     */
    private function rapikanDokumen(RealisasiProgramKerja $realisasi, Collection $dokumen): void
    {
        foreach ($dokumen as $berkas) {
            $path = $this->petaBerkas[$berkas->location] ?? null;

            if ($path === null) {
                continue;
            }

            RealisasiDokumen::query()
                ->where('realisasi_program_kerja_id', $realisasi->id)
                ->where('path', $path)
                ->update([
                    'size' => (int) $berkas->size,
                    'uploaded_at' => $berkas->created_at,
                    'created_at' => $berkas->created_at,
                    'updated_at' => $berkas->updated_at,
                ]);
        }
    }

    /**
     * Riwayat realisasi 2023 disusun dari peristiwa yang sempat tercatat: pembuatan,
     * penolakan laporan, dan pelaporan sisa anggaran.
     *
     * @param  Collection<int, object>  $penolakan
     * @param  Collection<int, object>  $pengembalian
     */
    private function tulisLog(RealisasiProgramKerja $realisasi, object $lama, Collection $penolakan, Collection $pengembalian): void
    {
        $baris = collect([[
            'user_id' => null,
            'status' => EnumStatusRealisasi::Draft->value,
            'description' => 'Realisasi "'.$lama->judul.'" dipindahkan dari aplikasi kinerja '.self::TAHUN.'.',
            'properties' => ['sumber' => 'kinerja-'.self::TAHUN, 'kode_lama' => $lama->id],
            'created_at' => $lama->created_at,
            'updated_at' => $lama->created_at,
        ]]);

        $baris = $baris->merge($penolakan->map(fn (object $pesan): array => [
            'user_id' => null,
            'status' => EnumStatusRealisasi::Revisi->value,
            'description' => 'Laporan realisasi dikembalikan untuk diperbaiki. Catatan: '.$pesan->keterangan,
            'properties' => ['sumber' => 'kinerja-'.self::TAHUN, 'catatan' => $pesan->keterangan],
            'created_at' => $pesan->created_at,
            'updated_at' => $pesan->updated_at,
        ]));

        $baris = $baris->merge($pengembalian->map(fn (object $item): array => [
            'user_id' => null,
            'status' => $realisasi->status->value,
            'description' => 'Laporan penyerapan anggaran: '.$item->keterangan,
            'properties' => ['sumber' => 'kinerja-'.self::TAHUN, 'nominal' => (float) $item->nominal],
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ]));

        if ($realisasi->status === EnumStatusRealisasi::Selesai) {
            $baris = $baris->push([
                'user_id' => null,
                'status' => EnumStatusRealisasi::Selesai->value,
                'description' => 'Laporan realisasi disetujui dan realisasi dinyatakan selesai.',
                'properties' => ['sumber' => 'kinerja-'.self::TAHUN],
                'created_at' => $lama->updated_at,
                'updated_at' => $lama->updated_at,
            ]);
        }

        $realisasi->logs()->createMany($baris->sortBy('created_at')->values()->all());
    }
}
