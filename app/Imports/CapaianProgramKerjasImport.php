<?php

namespace App\Imports;

use App\Enums\EnumJenisRealisasi;
use App\Enums\EnumStatusAnggaran;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumSumberReferensiProgramKerja;
use App\Exports\PetunjukKolomExport;
use App\Exports\ReferensiProgramKerjaExport;
use App\Filament\Actions\CatatCapaianProgramKerjaAction;
use App\Models\PaguAnggaran;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\RealisasiProgramKerja;
use App\Models\UnitKerja;
use App\Services\KodeReferensiProgramKerja;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Impor massal capaian program kerja dari berkas .xlsx/.csv, padanan berkas dari
 * {@see CatatCapaianProgramKerjaAction}.
 *
 * Tiap baris berkas menjadi satu realisasi program kerja bertanda
 * {@see EnumJenisRealisasi::TanpaAnggaran} yang langsung berstatus Selesai dan tidak
 * menyentuh anggaran sama sekali — persis seperti capaian yang dicatat satu per satu
 * dari halaman monitoring. Bedanya hanya satu: dokumen laporan tidak ikut diimpor
 * (berkas spreadsheet tidak bisa membawa PDF), sehingga capaian hasil impor lahir
 * tanpa laporan. Riwayat realisasinya mencatat sendiri bahwa capaian itu berasal dari
 * impor dan laporannya masih kosong, agar bisa dilengkapi belakangan lewat halaman
 * realisasi.
 *
 * Rujukan tiap baris adalah kode program kerja, yang daftarnya disertakan sebagai lembar
 * tersendiri pada berkas template ({@see ReferensiProgramKerjaExport}). Nama program
 * kerja sengaja tidak dipakai sebagai rujukan karena bisa kembar antar unit kerja.
 *
 * Kodenya boleh berbentuk dua rupa ({@see KodeReferensiProgramKerja}): angka polos yang
 * menunjuk pengajuan program kerja, atau berawalan `PK-` yang menunjuk program kerja pada
 * Daftar Program Kerja. Bentuk kedua itulah yang membuat program kerja yang belum pernah
 * diajukan tetap bisa dicatat capaiannya: pengajuannya dibuatkan lebih dahulu — tanpa
 * alokasi anggaran dan langsung berstatus diterima — lalu capaiannya menempel pada
 * pengajuan itu, sehingga datanya tetap utuh seperti capaian yang dicatat manual.
 *
 * Cakupan yang boleh diimpor diberikan lewat konteks form modal: `tahun_kerja_id` dan
 * `unit_kerja_ids`. Baris yang menunjuk program kerja di luar cakupan itu membatalkan
 * seluruh impor, sehingga scope data pengguna tidak bisa ditembus lewat berkas.
 */
class CapaianProgramKerjasImport extends Import
{
    /**
     * Pengajuan yang boleh dirujuk berkas ini, dikunci kodenya. Dibaca sekali lalu
     * ditahan karena validasi dan penyimpanan sama-sama memerlukannya per baris.
     *
     * @var Collection<int, PengajuanProgramKerja>|null
     */
    private ?Collection $pengajuans = null;

    /**
     * Pengajuan yang sama, dikelompokkan menurut program kerja yang ditawarkan. Dibuang
     * setiap kali ada pengajuan baru dibuatkan agar pengelompokannya ikut terbarui.
     *
     * @var Collection<int, Collection<int, PengajuanProgramKerja>>|null
     */
    private ?Collection $pengajuanPerPenawaran = null;

    /**
     * Program kerja Daftar Program Kerja yang boleh dirujuk, dikunci idnya.
     *
     * @var Collection<int, PenawaranProgramKerja>|null
     */
    private ?Collection $penawarans = null;

    /**
     * Catatan pelampauan pagu per unit kerja, dikunci id unit kerjanya supaya satu unit
     * hanya menghasilkan satu peringatan betapa pun banyak barisnya.
     *
     * @var array<int, string>
     */
    private array $warnings = [];

    public function headings(): array
    {
        return ['kode_program_kerja', 'tanggal_mulai', 'persentase_ketercapaian', 'deskripsi_kegiatan'];
    }

    public function templateColumns(): array
    {
        return [
            'kode_program_kerja',
            'nama_program_kerja',
            'tanggal_mulai',
            'tanggal_selesai',
            'nominal_digunakan',
            'persentase_ketercapaian',
            'deskripsi_kegiatan',
        ];
    }

    public function columnLabels(): array
    {
        return [
            'kode_program_kerja' => 'Kode Program Kerja',
            'nama_program_kerja' => 'Nama Program Kerja (Pengingat)',
            'tanggal_mulai' => 'Tanggal Mulai',
            'tanggal_selesai' => 'Tanggal Selesai',
            'nominal_digunakan' => 'Nominal Digunakan',
            'persentase_ketercapaian' => 'Persentase Ketercapaian',
            'deskripsi_kegiatan' => 'Deskripsi Kegiatan',
        ];
    }

    public function rules(): array
    {
        return [
            'kode_program_kerja' => ['required'],
            'nama_program_kerja' => ['nullable', 'string'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'nominal_digunakan' => ['nullable', 'numeric', 'min:0'],
            'persentase_ketercapaian' => ['required', 'numeric', 'min:0', 'max:100'],
            'deskripsi_kegiatan' => ['required', 'string'],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'kode_program_kerja' => 'kode program kerja',
            'tanggal_mulai' => 'tanggal mulai',
            'tanggal_selesai' => 'tanggal selesai',
            'nominal_digunakan' => 'nominal digunakan',
            'persentase_ketercapaian' => 'persentase ketercapaian',
            'deskripsi_kegiatan' => 'deskripsi kegiatan',
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh mendahului tanggal mulai.',
            'nominal_digunakan.numeric' => 'Nominal digunakan harus berupa angka rupiah tanpa titik maupun huruf.',
            'nominal_digunakan.min' => 'Nominal digunakan tidak boleh negatif.',
            'persentase_ketercapaian.numeric' => 'Persentase ketercapaian harus berupa angka 0 sampai 100, bukan format persen Excel.',
            'persentase_ketercapaian.max' => 'Persentase ketercapaian maksimal 100.',
        ];
    }

    public function validateRow(array $row, int $rowNumber, Closure $fail): void
    {
        $kode = KodeReferensiProgramKerja::urai($row['kode_program_kerja'] ?? null);
        $tertulis = (string) ($row['kode_program_kerja'] ?? '');

        if ($kode === null) {
            $fail("Baris {$rowNumber}: Kode program kerja belum diisi. Salin kodenya dari lembar \"Referensi Program Kerja\" pada berkas template.");

            return;
        }

        if ($kode['jenis'] === KodeReferensiProgramKerja::JENIS_DAFTAR) {
            $this->validasiKodeDaftar($kode['id'], $tertulis, $row, $rowNumber, $fail);

            return;
        }

        $pengajuan = $this->pengajuans()->get($kode['id']);

        if ($pengajuan === null) {
            $fail("Baris {$rowNumber}: Kode program kerja \"{$tertulis}\" tidak ditemukan pada tahun kerja ini, atau berada di luar unit kerja yang boleh Anda akses. Lihat lembar \"Referensi Program Kerja\" pada berkas template.");

            return;
        }

        $this->validasiKetercapaian(
            (int) $row['persentase_ketercapaian'],
            RealisasiProgramKerja::persentaseKetercapaianTertinggi((int) $pengajuan->getKey()),
            $this->namaProgram($pengajuan),
            $rowNumber,
            $fail,
        );
    }

    /**
     * Kode dari sisi Daftar Program Kerja. Program kerja yang belum pernah diajukan
     * tetap lolos — pengajuannya dibuatkan saat baris ini disimpan — tetapi yang punya
     * lebih dari satu pengajuan ditolak, karena capaiannya harus jelas menempel pada
     * pengajuan yang mana.
     *
     * @param  array<string, mixed>  $row
     */
    protected function validasiKodeDaftar(int $penawaranId, string $tertulis, array $row, int $rowNumber, Closure $fail): void
    {
        $penawaran = $this->penawarans()->get($penawaranId);

        if ($penawaran === null) {
            $fail("Baris {$rowNumber}: Kode program kerja \"{$tertulis}\" tidak ditemukan pada Daftar Program Kerja tahun kerja ini, atau berada di luar unit kerja yang boleh Anda akses. Lihat lembar \"Referensi Program Kerja\" pada berkas template.");

            return;
        }

        $pengajuans = $this->pengajuanPenawaran($penawaranId);

        if ($pengajuans->count() > 1) {
            $kodePengajuan = $pengajuans
                ->map(fn (PengajuanProgramKerja $pengajuan): string => KodeReferensiProgramKerja::pengajuan((int) $pengajuan->getKey()))
                ->implode(', ');

            $fail("Baris {$rowNumber}: Program kerja \"{$penawaran->name}\" punya lebih dari satu pengajuan ({$kodePengajuan}), sehingga capaiannya tidak jelas menempel pada yang mana. Ganti kodenya dengan salah satu kode pengajuan tersebut — kolom kode_pengajuan pada lembar \"Referensi Program Kerja\".");

            return;
        }

        $pengajuan = $pengajuans->first();

        $this->validasiKetercapaian(
            (int) $row['persentase_ketercapaian'],
            $pengajuan === null ? 0 : RealisasiProgramKerja::persentaseKetercapaianTertinggi((int) $pengajuan->getKey()),
            $penawaran->name ?? 'Program Kerja',
            $rowNumber,
            $fail,
        );
    }

    /**
     * Ketercapaian tidak boleh mundur dari capaian tertinggi yang sudah tercatat.
     */
    protected function validasiKetercapaian(int $persentase, int $minimum, string $nama, int $rowNumber, Closure $fail): void
    {
        if ($persentase < $minimum) {
            $fail("Baris {$rowNumber}: Ketercapaian \"{$nama}\" sudah tercatat {$minimum}%, sehingga capaian barunya tidak boleh lebih kecil dari itu.");
        }
    }

    public function storeRow(array $row): void
    {
        $pengajuan = $this->pengajuanUntuk($row['kode_program_kerja'] ?? null);

        if ($pengajuan === null) {
            return;
        }

        $mulai = Carbon::parse($this->keWaktu($row['tanggal_mulai']))->startOfDay();
        $selesai = filled($row['tanggal_selesai'] ?? null)
            ? Carbon::parse($this->keWaktu($row['tanggal_selesai']))->endOfDay()
            : $mulai->copy()->endOfDay();

        $persentase = (int) $row['persentase_ketercapaian'];
        $deskripsi = (string) $row['deskripsi_kegiatan'];
        $nominal = $this->nominal($row['nominal_digunakan'] ?? null);
        $beranggaran = $nominal > 0;

        $realisasi = RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuan->getKey(),
            'name' => $this->namaProgram($pengajuan),
            'jenis_realisasi' => $beranggaran ? EnumJenisRealisasi::Anggaran : EnumJenisRealisasi::TanpaAnggaran,
            // Deskripsi kegiatan sekaligus menjadi evaluasi pengerjaannya, sama seperti
            // capaian yang dicatat satu per satu dari halaman monitoring.
            'description' => $deskripsi,
            'evaluasi_pengerjaan' => $deskripsi,
            'start_datetime' => $mulai,
            'end_datetime' => $selesai,
            'anggaran_digunakan' => $nominal,
            'status' => EnumStatusRealisasi::Selesai,
            // Baris bernominal langsung tercatat cair pada tanggal pelaksanaannya:
            // yang diimpor adalah kegiatan yang sudah terjadi beserta dananya, jadi
            // tidak ada lagi yang menunggu dicairkan. Nominal yang disetujui disamakan
            // dengan yang dipakai, sehingga tidak ada selisih yang perlu dituntaskan.
            'nominal_disetujui' => $beranggaran ? $nominal : null,
            'dicairkan_at' => $beranggaran ? $mulai : null,
            'status_anggaran' => $beranggaran ? EnumStatusAnggaran::Habis : null,
            // Laporan tidak ikut terbawa berkas spreadsheet, jadi seluruh penanda
            // penyerahan dan persetujuan laporannya sengaja dibiarkan kosong — bukan
            // distempel seolah laporannya sudah ada.
            'persentase_ketercapaian' => $persentase,
            'dicatat_oleh_id' => auth()->id(),
        ]);

        $realisasi->catatLog(
            EnumStatusRealisasi::Selesai,
            auth()->id(),
            $beranggaran
                ? "Realisasi diimpor dari berkas spreadsheet pada Monitoring Program Kerja: ketercapaian {$persentase}% dengan penggunaan anggaran {$this->rupiah($nominal)} yang langsung tercatat cair pada tanggal pelaksanaan. Dokumen laporan belum dilampirkan dan dapat dilengkapi kemudian."
                : "Capaian diimpor dari berkas spreadsheet pada Monitoring Program Kerja: ketercapaian {$persentase}% tanpa penggunaan anggaran. Dokumen laporan belum dilampirkan dan dapat dilengkapi kemudian.",
        );

        if ($beranggaran) {
            $this->sesuaikanAlokasi($pengajuan);
        }
    }

    /**
     * Menaikkan alokasi anggaran pengajuan agar sekurang-kurangnya menutupi seluruh
     * realisasi beranggaran yang menempel padanya — termasuk pengajuan bernilai nol yang
     * dibuatkan proses impor ini. Alokasi yang sudah cukup tidak diutak-atik, karena
     * realisasi memang seharusnya memakai alokasi yang sudah disetujui.
     *
     * Bila alokasi barunya membuat unit kerja melampaui pagu, impor tetap diteruskan —
     * hanya jalur inilah yang boleh melampaui pagu — tetapi keadaannya dicatat pada
     * riwayat dan catatan verifikasi pengajuan, lalu disampaikan sebagai peringatan
     * kepada pengimpor.
     */
    protected function sesuaikanAlokasi(PengajuanProgramKerja $pengajuan): void
    {
        $terpakai = (float) RealisasiProgramKerja::query()
            ->where('pengajuan_program_kerja_id', $pengajuan->getKey())
            ->where('jenis_realisasi', EnumJenisRealisasi::Anggaran->value)
            ->whereNotIn('status', [
                EnumStatusRealisasi::Draft->value,
                EnumStatusRealisasi::Ditolak->value,
                EnumStatusRealisasi::Dibatalkan->value,
            ])
            ->sum('anggaran_digunakan');

        $alokasiLama = (float) $pengajuan->alokasi_anggaran;

        if ($terpakai <= $alokasiLama) {
            return;
        }

        $pengajuan->forceFill(['alokasi_anggaran' => $terpakai]);

        $pelampauan = $this->pelampauanPagu($pengajuan, $terpakai - $alokasiLama);

        $catatan = 'Alokasi anggaran disesuaikan dari '.$this->rupiah($alokasiLama).' menjadi '
            .$this->rupiah($terpakai).' mengikuti realisasi yang diimpor pada Monitoring Program Kerja.';

        if ($pelampauan !== null) {
            $catatan .= ' '.$pelampauan;

            $this->warnings[$pengajuan->unit_kerja_id] = $pelampauan;
        }

        $pengajuan->catatan_verifikasi = $catatan;
        $pengajuan->save();

        $pengajuan->catatLog(EnumStatusPengajuan::Diterima, auth()->id(), $catatan);
    }

    /**
     * Kalimat penanda bahwa alokasi unit kerja melampaui pagunya, atau null bila masih
     * di dalam pagu. Sisa pagu dihitung sama seperti form pengajuan: pagu unit kerja
     * dikurangi seluruh alokasi pengajuan yang tidak ditolak, ditambah penyesuaian dari
     * selisih anggaran yang sudah dituntaskan.
     */
    protected function pelampauanPagu(PengajuanProgramKerja $pengajuan, float $tambahan): ?string
    {
        $unitKerjaId = (int) $pengajuan->unit_kerja_id;
        $tahunKerjaId = $this->tahunKerjaId();

        if ($tahunKerjaId === null) {
            return null;
        }

        $pagu = (float) (PaguAnggaran::query()
            ->where('tahun_kerja_id', $tahunKerjaId)
            ->where('unit_kerja_id', $unitKerjaId)
            ->value('amount') ?? 0);

        $dialokasikan = (float) PengajuanProgramKerja::query()
            ->where('status', '!=', EnumStatusPengajuan::Ditolak->value)
            ->where('unit_kerja_id', $unitKerjaId)
            ->whereHas('penawaranProgramKerja', fn (Builder $query): Builder => $query
                ->where('tahun_kerja_id', $tahunKerjaId))
            ->whereKeyNot($pengajuan->getKey())
            ->sum('alokasi_anggaran');

        // Pengajuan yang sedang disesuaikan dihitung terpisah karena nilainya di
        // database belum tentu tersimpan saat pemeriksaan ini berjalan.
        $dialokasikan += (float) $pengajuan->alokasi_anggaran;

        $penyesuaian = RealisasiProgramKerja::totalPenyesuaianAnggaran($unitKerjaId, $tahunKerjaId);
        $pelampauan = $dialokasikan - ($pagu + $penyesuaian);

        if ($pelampauan <= 0) {
            return null;
        }

        $nama = $pengajuan->unitKerja?->name ?? UnitKerja::find($unitKerjaId)?->name ?? 'Unit kerja';

        return "Alokasi {$nama} kini {$this->rupiah($dialokasikan)}, melampaui pagu anggarannya "
            .$this->rupiah($pagu + $penyesuaian)." sebesar {$this->rupiah($pelampauan)} "
            .'karena penambahan '.$this->rupiah($tambahan).' dari impor realisasi.';
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return array_values($this->warnings);
    }

    public function sampleRows(): array
    {
        // Contohnya memakai bentuk kode yang sama dengan lembar referensi yang ikut
        // diunduh, supaya yang disalin pengguna dan yang dicontohkan tidak berbeda rupa.
        [$kodeContoh, $namaContoh] = $this->dariDaftarProgramKerja()
            ? $this->contohDaftarProgramKerja()
            : $this->contohPengajuan();

        return [
            [
                $kodeContoh,
                $namaContoh,
                '2026-03-10',
                '2026-03-12',
                '',
                70,
                'Workshop terlaksana dua sesi dengan 45 peserta; materi dan notulen terlampir.',
            ],
        ];
    }

    public function templateFilename(): string
    {
        return 'template-impor-capaian-program-kerja';
    }

    public function templateTitle(): ?string
    {
        return 'Template Impor Capaian';
    }

    public function templateSubtitle(): ?string
    {
        return 'Isi mulai baris di bawah contoh, satu baris untuk satu capaian. Kode program kerja '
            .'disalin dari lembar "Referensi Program Kerja" (diambil dari '.$this->sumberReferensi()->getLabel().'); '
            .'aturan tiap kolom ada di lembar "Petunjuk Pengisian". '
            .'Kolom nominal_digunakan boleh dikosongkan untuk kegiatan tanpa anggaran; bila diisi, anggarannya langsung tercatat terpakai. '
            .'Jangan mengubah baris key kolom (baris kecil berhuruf miring) — baris itulah yang dibaca saat berkas diimpor.';
    }

    public function templateSheets(): array
    {
        return [
            new PetunjukKolomExport(
                'Petunjuk Pengisian',
                'Aturan pengisian tiap kolom pada lembar template. Satu baris berkas menjadi satu realisasi yang langsung '
                    .'berstatus Selesai: tanpa anggaran bila kolom nominal_digunakan dikosongkan, atau beranggaran dan langsung '
                    .'tercatat cair bila kolom itu diisi.',
                $this->petunjukKolom(),
            ),
            new ReferensiProgramKerjaExport(
                $this->unitKerjaIds(),
                $this->tahunKerjaId(),
                $this->context('nama_tahun_kerja'),
                $this->sumberReferensi(),
            ),
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    protected function petunjukKolom(): array
    {
        return [
            ['kode_program_kerja', 'Wajib', 'Angka atau PK-<angka>', 'Rujukan program kerja yang capaiannya dicatat. Salin apa adanya dari kolom kode_program_kerja pada lembar "Referensi Program Kerja". Angka polos menunjuk pengajuan program kerja; kode berawalan "'.KodeReferensiProgramKerja::AWALAN_DAFTAR.'" menunjuk program kerja pada Daftar Program Kerja, dan bila program kerja itu belum pernah diajukan, pengajuannya dibuatkan otomatis tanpa alokasi anggaran saat impor berjalan.'],
            ['nama_program_kerja', 'Opsional', 'Teks', 'Hanya pengingat agar kode tidak tertukar saat mengisi. Nilainya tidak disimpan — yang menentukan tetap kode program kerja.'],
            ['tanggal_mulai', 'Wajib', 'YYYY-MM-DD', 'Tanggal kegiatan dilaksanakan. Untuk kegiatan satu hari, cukup isi kolom ini.'],
            ['tanggal_selesai', 'Opsional', 'YYYY-MM-DD', 'Diisi hanya bila kegiatan berlangsung lebih dari sehari, dan tidak boleh mendahului tanggal mulai. Dikosongkan berarti selesai pada hari yang sama.'],
            ['nominal_digunakan', 'Opsional', 'Angka rupiah', 'Dikosongkan berarti kegiatan tidak memakai anggaran — capaiannya tercatat tanpa menyentuh penyerapan, sama seperti Catat Capaian. Diisi berarti kegiatan memakai anggaran sebesar itu: realisasinya langsung tercatat cair pada tanggal mulai, sehingga ikut terhitung sebagai penyerapan anggaran pada Monitoring dan Buku Anggaran. Alokasi pengajuan program kerjanya menyesuaikan sendiri bila kurang, dan bila sampai melampaui pagu unit kerja, impor tetap diteruskan namun keadaannya dicatat pada pengajuan dan diberitahukan setelah impor selesai.'],
            ['persentase_ketercapaian', 'Wajib', 'Angka 0–100', 'Seberapa besar target program kerja tercapai. Tulis angka biasa (mis. 70), bukan sel berformat persen. Tidak boleh lebih kecil dari kolom capaian_terakhir pada lembar referensi — ketercapaian tidak boleh mundur.'],
            ['deskripsi_kegiatan', 'Wajib', 'Teks', 'Uraian kegiatan yang dilaksanakan beserta hasilnya, menjadi dasar penilaian ketercapaian.'],
            ['(dokumen laporan)', 'Tidak diimpor', '—', 'Berkas spreadsheet tidak bisa membawa PDF, jadi capaian hasil impor lahir tanpa dokumen laporan. Laporannya dapat dilampirkan kemudian lewat halaman Realisasi Program Kerja.'],
        ];
    }

    /**
     * Program kerja yang boleh dirujuk berkas ini, dikunci kode (id pengajuan).
     *
     * @return Collection<int, PengajuanProgramKerja>
     */
    protected function pengajuans(): Collection
    {
        if ($this->pengajuans !== null) {
            return $this->pengajuans;
        }

        if ($this->tahunKerjaId() === null || $this->unitKerjaIds() === []) {
            return $this->pengajuans = collect();
        }

        return $this->pengajuans = PengajuanProgramKerja::query()
            ->dapatDicatatCapaiannya($this->tahunKerjaId(), $this->unitKerjaIds())
            ->with('penawaranProgramKerja:id,name')
            ->get()
            ->keyBy(fn (PengajuanProgramKerja $pengajuan): int => (int) $pengajuan->getKey());
    }

    /**
     * Asal daftar pada lembar referensi, dipilih pengguna saat mengunduh template.
     * Pilihan asing dikembalikan ke bawaannya, jadi berkas template selalu terbentuk.
     */
    protected function sumberReferensi(): EnumSumberReferensiProgramKerja
    {
        return EnumSumberReferensiProgramKerja::dariNilai(
            $this->context('sumber_referensi', EnumSumberReferensiProgramKerja::bawaan()),
        );
    }

    protected function dariDaftarProgramKerja(): bool
    {
        return $this->sumberReferensi() === EnumSumberReferensiProgramKerja::DaftarProgramKerja;
    }

    /**
     * Kode dan nama contoh dari sisi pengajuan.
     *
     * @return array{0: string, 1: string}
     */
    protected function contohPengajuan(): array
    {
        $contoh = $this->pengajuans()->first();

        return $contoh !== null
            ? [KodeReferensiProgramKerja::pengajuan((int) $contoh->getKey()), $this->namaProgram($contoh)]
            : ['12', 'Workshop Penulisan Karya Ilmiah'];
    }

    /**
     * Kode dan nama contoh dari sisi Daftar Program Kerja.
     *
     * @return array{0: string, 1: string}
     */
    protected function contohDaftarProgramKerja(): array
    {
        $contoh = $this->penawarans()->first();

        return $contoh !== null
            ? [KodeReferensiProgramKerja::daftarProgramKerja((int) $contoh->getKey()), (string) $contoh->name]
            : [KodeReferensiProgramKerja::daftarProgramKerja(7), 'Workshop Penulisan Karya Ilmiah'];
    }

    /**
     * @return array<int, int>
     */
    protected function unitKerjaIds(): array
    {
        return array_map('intval', (array) $this->context('unit_kerja_ids', []));
    }

    protected function tahunKerjaId(): ?int
    {
        $id = $this->context('tahun_kerja_id');

        return filled($id) ? (int) $id : null;
    }

    /**
     * Pengajuan tempat capaian sebuah baris dicatat. Kode dari sisi Daftar Program Kerja
     * yang belum pernah diajukan dibuatkan pengajuannya lebih dahulu, sehingga
     * capaiannya tetap menempel pada data yang utuh.
     */
    protected function pengajuanUntuk(mixed $nilai): ?PengajuanProgramKerja
    {
        $kode = KodeReferensiProgramKerja::urai($nilai);

        if ($kode === null) {
            return null;
        }

        if ($kode['jenis'] === KodeReferensiProgramKerja::JENIS_PENGAJUAN) {
            return $this->pengajuans()->get($kode['id']);
        }

        $penawaran = $this->penawarans()->get($kode['id']);

        if ($penawaran === null) {
            return null;
        }

        return $this->pengajuanPenawaran($penawaran->getKey())->first()
            ?? $this->buatkanPengajuan($penawaran);
    }

    /**
     * Membuatkan pengajuan bagi program kerja yang capaiannya diimpor namun belum
     * pernah diajukan: tanpa alokasi anggaran dan langsung berstatus diterima, karena
     * capaian yang diimpor memang tidak menyentuh anggaran sama sekali. Riwayatnya
     * mencatat sendiri bahwa pengajuan ini lahir dari impor, bukan diajukan unit kerja.
     *
     * Pengajuan yang baru dibuat langsung dimasukkan ke daftar yang ditahan, supaya
     * baris berikutnya yang menunjuk program kerja sama memakai pengajuan ini — bukan
     * membuat pengajuan kembar.
     */
    protected function buatkanPengajuan(PenawaranProgramKerja $penawaran): PengajuanProgramKerja
    {
        $pengajuan = PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->getKey(),
            'unit_kerja_id' => $penawaran->unit_kerja_id,
            'user_id' => auth()->id(),
            'alokasi_anggaran' => 0,
            'deskripsi_kegiatan' => 'Pengajuan dibuat otomatis saat impor capaian program kerja, tanpa alokasi anggaran.',
            'status' => EnumStatusPengajuan::Diterima,
            'diverifikasi_at' => now(),
            'verifikator_id' => auth()->id(),
        ]);

        $pengajuan->catatLog(
            EnumStatusPengajuan::Diterima,
            auth()->id(),
            'Pengajuan dibuat otomatis dari impor capaian program kerja pada Monitoring Program Kerja: '
                .'program kerja ini belum pernah diajukan, sehingga capaiannya perlu induk pengajuan. '
                .'Alokasi anggarannya nol karena capaian yang diimpor tidak menyentuh anggaran.',
        );

        $pengajuan->setRelation('penawaranProgramKerja', $penawaran);

        $this->pengajuans()->put((int) $pengajuan->getKey(), $pengajuan);
        $this->pengajuanPerPenawaran = null;

        return $pengajuan;
    }

    /**
     * Pengajuan yang capaiannya boleh dicatat pada sebuah program kerja Daftar Program
     * Kerja.
     *
     * @return Collection<int, PengajuanProgramKerja>
     */
    protected function pengajuanPenawaran(int $penawaranId): Collection
    {
        $this->pengajuanPerPenawaran ??= $this->pengajuans()->groupBy('penawaran_program_kerja_id');

        /** @var Collection<int, PengajuanProgramKerja> $pengajuans */
        $pengajuans = $this->pengajuanPerPenawaran->get($penawaranId) ?? collect();

        return $pengajuans;
    }

    /**
     * Program kerja pada Daftar Program Kerja yang boleh dirujuk berkas ini, dikunci
     * idnya. Cakupannya sama dengan lembar referensi: tahun kerja yang dipantau dan unit
     * kerja yang boleh diakses pengguna.
     *
     * @return Collection<int, PenawaranProgramKerja>
     */
    protected function penawarans(): Collection
    {
        if ($this->penawarans !== null) {
            return $this->penawarans;
        }

        if ($this->tahunKerjaId() === null || $this->unitKerjaIds() === []) {
            return $this->penawarans = collect();
        }

        return $this->penawarans = PenawaranProgramKerja::query()
            ->where('tahun_kerja_id', $this->tahunKerjaId())
            ->whereIn('unit_kerja_id', $this->unitKerjaIds())
            ->where('is_active', true)
            ->get()
            ->keyBy(fn (PenawaranProgramKerja $penawaran): int => (int) $penawaran->getKey());
    }

    /**
     * Nominal dibaca longgar: sel .xlsx bisa berupa angka, sedangkan .csv kerap membawa
     * pemisah ribuan atau awalan "Rp" hasil salin-tempel. Kosong berarti kegiatan itu
     * tidak memakai anggaran sama sekali.
     */
    protected function nominal(mixed $nilai): float
    {
        if (blank($nilai)) {
            return 0.0;
        }

        if (is_numeric($nilai)) {
            return max(0.0, (float) $nilai);
        }

        $angka = preg_replace('/[^0-9,.\-]/', '', (string) $nilai) ?? '';
        $angka = str_replace(['.', ','], ['', '.'], $angka);

        return is_numeric($angka) ? max(0.0, (float) $angka) : 0.0;
    }

    protected function rupiah(float $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }

    /**
     * Sel tanggal .xlsx terbaca sebagai objek DateTime, sedangkan .csv sebagai teks.
     */
    protected function keWaktu(mixed $nilai): string
    {
        return $nilai instanceof DateTimeInterface
            ? $nilai->format('Y-m-d')
            : (string) $nilai;
    }

    protected function namaProgram(PengajuanProgramKerja $pengajuan): string
    {
        return $pengajuan->penawaranProgramKerja?->name ?? 'Program Kerja';
    }
}
