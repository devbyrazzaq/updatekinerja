<?php

namespace Tests\Feature;

use App\Enums\EnumJenisRealisasi;
use App\Enums\EnumStatusAnggaran;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Enums\EnumSumberReferensiProgramKerja;
use App\Filament\Actions\ImporCapaianProgramKerjaAction;
use App\Filament\Pages\MonitoringProgramKerja;
use App\Imports\CapaianProgramKerjasImport;
use App\Imports\ImportResult;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\PaguAnggaran;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\RealisasiProgramKerja;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\KodeReferensiProgramKerja;
use App\Services\MonitoringAnggaran;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Impor massal capaian program kerja dari berkas spreadsheet: tercatat sama seperti
 * capaian yang dicatat satu per satu — realisasi tuntas tanpa anggaran — hanya saja
 * lahir tanpa dokumen laporan, dan berkas templatenya membawa sendiri lembar petunjuk
 * serta lembar referensi kode program kerja.
 */
class ImporCapaianMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private TahunKerja $tahunKerja;

    private TahunKerja $tahunLain;

    private UnitKerja $unitA;

    private UnitKerja $unitB;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $periode = Periode::create([
            'name' => 'P1',
            'start_datetime' => Carbon::create(2026, 1, 1),
            'end_datetime' => Carbon::create(2027, 12, 31),
        ]);

        $this->tahunKerja = TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA 2026',
            'start_datetime' => Carbon::create(2026, 1, 1),
            'end_datetime' => Carbon::create(2026, 12, 31),
            'status' => EnumStatusTahunKerja::Berjalan,
        ]);

        $this->tahunLain = TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA 2027',
            'start_datetime' => Carbon::create(2027, 1, 1),
            'end_datetime' => Carbon::create(2027, 12, 31),
            'status' => EnumStatusTahunKerja::Perencanaan,
        ]);

        $this->unitA = UnitKerja::create(['name' => 'Unit A']);
        $this->unitB = UnitKerja::create(['name' => 'Unit B']);

        $this->superAdmin = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);
        $this->superAdmin->givePermissionTo(Permission::findOrCreate('bypass_data_scope', 'web'));

        $this->actingAs($this->superAdmin);
    }

    public function test_capaian_terimpor_sebagai_realisasi_selesai_tanpa_anggaran_dan_tanpa_laporan(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 'Workshop Penulisan');

        $path = $this->tulisCsv("{$pengajuan->id},2026-03-10,2026-03-12,70,\"Workshop terlaksana dua sesi.\"\n");

        $result = $this->impor($path);

        $this->assertFalse($result->failed());
        $this->assertSame(1, $result->imported);

        $realisasi = RealisasiProgramKerja::query()->firstOrFail();

        $this->assertSame($pengajuan->id, $realisasi->pengajuan_program_kerja_id);
        $this->assertSame('Workshop Penulisan', $realisasi->name);
        $this->assertSame(EnumStatusRealisasi::Selesai, $realisasi->status);
        $this->assertSame(EnumJenisRealisasi::TanpaAnggaran, $realisasi->jenis_realisasi);
        $this->assertSame(70, $realisasi->persentase_ketercapaian);
        $this->assertSame(0.0, (float) $realisasi->anggaran_digunakan);
        $this->assertNull($realisasi->status_anggaran);
        $this->assertNull($realisasi->dicairkan_at);
        $this->assertSame($this->superAdmin->id, $realisasi->dicatat_oleh_id);
        $this->assertSame('2026-03-10', $realisasi->start_datetime->toDateString());
        $this->assertSame('2026-03-12', $realisasi->end_datetime->toDateString());

        // Berkas spreadsheet tidak membawa PDF, jadi laporannya kosong dan tidak
        // distempel seolah sudah diserahkan maupun disetujui.
        $this->assertEmpty((array) $realisasi->laporan_path);
        $this->assertNull($realisasi->laporan_diserahkan_at);
        $this->assertNull($realisasi->laporan_disetujui_at);
        $this->assertNull($realisasi->verifikator_laporan_id);

        // Yang otomatis dibuat hanyalah catatan riwayatnya, sekaligus penanda bahwa
        // laporannya masih bisa dilengkapi belakangan.
        $catatan = $realisasi->logs()->latest('id')->first();

        $this->assertNotNull($catatan);
        $this->assertStringContainsString('diimpor dari berkas spreadsheet', $catatan->description);
        $this->assertStringContainsString('dilengkapi kemudian', $catatan->description);

        @unlink($path);
    }

    public function test_tanggal_selesai_boleh_kosong_untuk_kegiatan_satu_hari(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 'Pelatihan Sehari');

        $path = $this->tulisCsv("{$pengajuan->id},2026-04-01,,100,\"Pelatihan satu hari penuh.\"\n");

        $this->assertSame(1, $this->impor($path)->imported);

        $realisasi = RealisasiProgramKerja::query()->firstOrFail();

        $this->assertSame('2026-04-01', $realisasi->start_datetime->toDateString());
        $this->assertSame('2026-04-01', $realisasi->end_datetime->toDateString());

        @unlink($path);
    }

    public function test_kode_program_kerja_di_luar_cakupan_membatalkan_seluruh_impor(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 'Workshop Penulisan');
        $pengajuanLuar = $this->seedPengajuan($this->unitB, 'Kegiatan Unit B');

        // Baris pertama sah, baris kedua menunjuk unit di luar cakupan.
        $path = $this->tulisCsv(
            "{$pengajuan->id},2026-03-10,,70,\"Sah.\"\n"
            ."{$pengajuanLuar->id},2026-03-11,,80,\"Di luar cakupan.\"\n"
        );

        $result = $this->impor($path, unitKerjaIds: [$this->unitA->id]);

        $this->assertTrue($result->failed());
        $this->assertStringContainsString('tidak ditemukan pada tahun kerja ini', implode(' ', $result->errors));
        $this->assertSame(0, RealisasiProgramKerja::query()->count());

        @unlink($path);
    }

    public function test_program_kerja_tahun_kerja_lain_ditolak(): void
    {
        $pengajuanTahunLain = $this->seedPengajuan($this->unitA, 'Kegiatan Tahun Depan', $this->tahunLain);

        $path = $this->tulisCsv("{$pengajuanTahunLain->id},2026-03-10,,70,\"Tahun lain.\"\n");

        $this->assertTrue($this->impor($path)->failed());
        $this->assertSame(0, RealisasiProgramKerja::query()->count());

        @unlink($path);
    }

    public function test_ketercapaian_tidak_boleh_mundur_dari_capaian_terakhir(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 'Workshop Penulisan');

        RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'name' => 'Capaian pertama',
            'anggaran_digunakan' => 0,
            'status' => EnumStatusRealisasi::Selesai,
            'persentase_ketercapaian' => 60,
        ]);

        $path = $this->tulisCsv("{$pengajuan->id},2026-05-01,,50,\"Lanjutan kegiatan.\"\n");

        $result = $this->impor($path);

        $this->assertTrue($result->failed());
        $this->assertStringContainsString('sudah tercatat 60%', implode(' ', $result->errors));
        $this->assertSame(1, RealisasiProgramKerja::query()->count());

        @unlink($path);
    }

    public function test_persentase_di_luar_rentang_membatalkan_impor(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 'Workshop Penulisan');

        $path = $this->tulisCsv("{$pengajuan->id},2026-05-01,,120,\"Melebihi seratus.\"\n");

        $this->assertTrue($this->impor($path)->failed());
        $this->assertSame(0, RealisasiProgramKerja::query()->count());

        @unlink($path);
    }

    public function test_tanggal_selesai_tidak_boleh_mendahului_tanggal_mulai(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 'Workshop Penulisan');

        $path = $this->tulisCsv("{$pengajuan->id},2026-05-10,2026-05-01,70,\"Terbalik.\"\n");

        $result = $this->impor($path);

        $this->assertTrue($result->failed());
        $this->assertStringContainsString('mendahului tanggal mulai', implode(' ', $result->errors));

        @unlink($path);
    }

    public function test_template_membawa_lembar_petunjuk_dan_referensi_kode(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 'Workshop Penulisan');
        $this->seedPengajuan($this->unitB, 'Kegiatan Unit B');

        $lembar = $this->bacaTemplate(unitKerjaIds: [$this->unitA->id]);

        $this->assertSame(
            ['Template Impor Capaian', 'Petunjuk Pengisian', 'Referensi Program Kerja'],
            array_keys($lembar),
        );

        // Lembar pertama — yang dibaca saat impor — memuat seluruh kolom wajibnya.
        $keyIsian = $this->cariBaris($lembar['Template Impor Capaian'], 'kode_program_kerja');
        $this->assertNotNull($keyIsian);
        foreach ((new CapaianProgramKerjasImport)->headings() as $kolom) {
            $this->assertContains($kolom, $keyIsian);
        }

        // Petunjuk menyebutkan aturan tiap kolom, termasuk laporan yang tidak diimpor.
        $petunjuk = $this->rataTeks($lembar['Petunjuk Pengisian']);
        $this->assertStringContainsString('persentase_ketercapaian', $petunjuk);
        $this->assertStringContainsString('dokumen laporan', $petunjuk);

        // Referensi memuat kode unit yang boleh diakses, dan hanya itu.
        $referensi = $this->rataTeks($lembar['Referensi Program Kerja']);
        $this->assertStringContainsString('Workshop Penulisan', $referensi);
        $this->assertStringContainsString((string) $pengajuan->id, $referensi);
        $this->assertStringNotContainsString('Kegiatan Unit B', $referensi);
    }

    public function test_baris_bernominal_tercatat_sebagai_realisasi_beranggaran_yang_langsung_cair(): void
    {
        $this->seedPagu($this->unitA, 50_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 'Workshop Penulisan');

        $path = $this->tulisCsvBernominal("{$pengajuan->id},2026-03-10,2026-03-12,2500000,70,\"Workshop terlaksana.\"\n");

        $result = $this->impor($path);

        $this->assertFalse($result->failed(), implode(' ', $result->errors));
        $this->assertFalse($result->hasWarnings());

        $realisasi = RealisasiProgramKerja::query()->firstOrFail();

        $this->assertSame(EnumJenisRealisasi::Anggaran, $realisasi->jenis_realisasi);
        $this->assertSame(EnumStatusRealisasi::Selesai, $realisasi->status);
        $this->assertSame(2_500_000.0, (float) $realisasi->anggaran_digunakan);
        $this->assertSame(2_500_000.0, (float) $realisasi->nominal_disetujui);
        $this->assertSame(EnumStatusAnggaran::Habis, $realisasi->status_anggaran);
        $this->assertSame('2026-03-10', $realisasi->dicairkan_at?->toDateString());

        // Karena sudah bertanggal cair, nominalnya ikut terhitung sebagai penyerapan.
        $ringkasan = MonitoringAnggaran::untukUnits([$this->unitA->id], $this->tahunKerja)->ringkasan();

        $this->assertSame(2_500_000.0, $ringkasan->terserap());

        @unlink($path);
    }

    public function test_baris_tanpa_nominal_tetap_tidak_menyentuh_anggaran(): void
    {
        $this->seedPagu($this->unitA, 50_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 'Workshop Penulisan');

        $path = $this->tulisCsvBernominal("{$pengajuan->id},2026-03-10,,,70,\"Workshop terlaksana.\"\n");

        $result = $this->impor($path);

        $this->assertFalse($result->failed(), implode(' ', $result->errors));

        $realisasi = RealisasiProgramKerja::query()->firstOrFail();

        $this->assertSame(EnumJenisRealisasi::TanpaAnggaran, $realisasi->jenis_realisasi);
        $this->assertSame(0.0, (float) $realisasi->anggaran_digunakan);
        $this->assertNull($realisasi->dicairkan_at);
        $this->assertNull($realisasi->status_anggaran);

        $ringkasan = MonitoringAnggaran::untukUnits([$this->unitA->id], $this->tahunKerja)->ringkasan();

        $this->assertSame(0.0, $ringkasan->terserap());

        @unlink($path);
    }

    public function test_alokasi_pengajuan_yang_dibuatkan_impor_menyesuaikan_nominalnya(): void
    {
        $this->seedPagu($this->unitA, 50_000_000);
        $penawaran = $this->seedPenawaran($this->unitA, 'Pelatihan Belum Diajukan');
        $kode = KodeReferensiProgramKerja::daftarProgramKerja($penawaran->id);

        $path = $this->tulisCsvBernominal(
            "{$kode},2026-04-01,,3000000,60,\"Angkatan pertama.\"\n"
            ."{$kode},2026-05-01,,2000000,80,\"Angkatan kedua.\"\n",
        );

        $result = $this->impor($path);

        $this->assertFalse($result->failed(), implode(' ', $result->errors));
        $this->assertFalse($result->hasWarnings());

        // Satu pengajuan saja, dan alokasinya menampung kedua nominal yang diimpor.
        $pengajuan = PengajuanProgramKerja::query()->sole();

        $this->assertSame(5_000_000.0, (float) $pengajuan->alokasi_anggaran);
        $this->assertStringContainsString('Alokasi anggaran disesuaikan', (string) $pengajuan->catatan_verifikasi);

        @unlink($path);
    }

    public function test_alokasi_pengajuan_yang_sudah_cukup_tidak_diubah(): void
    {
        $this->seedPagu($this->unitA, 50_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 'Workshop Penulisan');

        $path = $this->tulisCsvBernominal("{$pengajuan->id},2026-03-10,,1000000,70,\"Workshop terlaksana.\"\n");

        $this->impor($path);

        // Pengajuan ini beralokasi 8 juta, jadi realisasi 1 juta memang muat.
        $this->assertSame(8_000_000.0, (float) $pengajuan->fresh()->alokasi_anggaran);
        $this->assertNull($pengajuan->fresh()->catatan_verifikasi);

        @unlink($path);
    }

    public function test_nominal_melebihi_pagu_tetap_tersimpan_namun_dicatat(): void
    {
        $this->seedPagu($this->unitA, 4_000_000);
        $penawaran = $this->seedPenawaran($this->unitA, 'Pelatihan Belum Diajukan');
        $kode = KodeReferensiProgramKerja::daftarProgramKerja($penawaran->id);

        $path = $this->tulisCsvBernominal("{$kode},2026-04-01,,6000000,60,\"Pelatihan terlaksana.\"\n");

        $result = $this->impor($path);

        // Hanya jalur impor inilah yang boleh melampaui pagu — barisnya tetap tersimpan.
        $this->assertFalse($result->failed(), implode(' ', $result->errors));
        $this->assertSame(1, $result->imported);
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString('melampaui pagu', implode(' ', $result->warnings));
        $this->assertStringContainsString('Unit A', implode(' ', $result->warnings));

        $pengajuan = PengajuanProgramKerja::query()->sole();

        $this->assertSame(6_000_000.0, (float) $pengajuan->alokasi_anggaran);
        $this->assertStringContainsString('melampaui pagu', (string) $pengajuan->catatan_verifikasi);
        $this->assertStringContainsString(
            'melampaui pagu',
            (string) $pengajuan->logs()->latest('id')->first()?->description,
        );

        @unlink($path);
    }

    public function test_lembar_referensi_dari_daftar_program_kerja_memuat_yang_belum_diajukan(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 'Workshop Penulisan');
        $penawaran = $this->seedPenawaran($this->unitA, 'Pelatihan Belum Diajukan');
        $this->seedPengajuan($this->unitB, 'Kegiatan Unit B');

        $lembar = $this->bacaTemplate(
            unitKerjaIds: [$this->unitA->id],
            sumber: EnumSumberReferensiProgramKerja::DaftarProgramKerja,
        );

        $referensi = $this->rataTeks($lembar['Referensi Program Kerja']);

        // Program kerja yang sudah diajukan tetap berkode dan siap diimpor.
        $this->assertStringContainsString('Workshop Penulisan', $referensi);
        $this->assertStringContainsString((string) $pengajuan->id, $referensi);
        $this->assertStringContainsString('Sudah diajukan', $referensi);

        // Yang belum diajukan ikut terdaftar, tetap berkode, dan ditandai statusnya.
        $this->assertStringContainsString('Pelatihan Belum Diajukan', $referensi);
        $this->assertStringContainsString('Belum diajukan', $referensi);

        $baris = $this->cariBaris($lembar['Referensi Program Kerja'], 'Pelatihan Belum Diajukan');
        $this->assertNotNull($baris);
        $this->assertSame(
            KodeReferensiProgramKerja::daftarProgramKerja($penawaran->id),
            (string) $baris[0],
        );
        $this->assertSame('Dibuatkan saat impor', (string) $baris[7]);

        // Cakupan unit kerja tetap berlaku apa pun sumbernya.
        $this->assertStringNotContainsString('Kegiatan Unit B', $referensi);
    }

    public function test_kode_daftar_program_kerja_membuatkan_pengajuan_yang_belum_ada(): void
    {
        $penawaran = $this->seedPenawaran($this->unitA, 'Pelatihan Belum Diajukan');

        $kode = KodeReferensiProgramKerja::daftarProgramKerja($penawaran->id);
        $path = $this->tulisCsv("{$kode},2026-04-01,2026-04-02,60,\"Pelatihan terlaksana satu angkatan.\"\n");

        $result = $this->impor($path);

        $this->assertFalse($result->failed(), implode(' ', $result->errors));
        $this->assertSame(1, $result->imported);

        // Pengajuannya dibuatkan tanpa anggaran dan langsung diterima, agar capaiannya
        // punya induk yang sah tanpa mengubah penyerapan anggaran.
        $pengajuan = PengajuanProgramKerja::query()->firstOrFail();

        $this->assertSame($penawaran->id, $pengajuan->penawaran_program_kerja_id);
        $this->assertSame($this->unitA->id, $pengajuan->unit_kerja_id);
        $this->assertSame(EnumStatusPengajuan::Diterima, $pengajuan->status);
        $this->assertSame(0.0, (float) $pengajuan->alokasi_anggaran);
        $this->assertNotNull($pengajuan->diverifikasi_at);
        $this->assertSame($this->superAdmin->id, $pengajuan->verifikator_id);
        $this->assertStringContainsString(
            'dibuat otomatis dari impor capaian',
            (string) $pengajuan->logs()->latest('id')->first()?->description,
        );

        // Capaiannya menempel pada pengajuan yang baru dibuat itu.
        $realisasi = RealisasiProgramKerja::query()->firstOrFail();

        $this->assertSame($pengajuan->id, $realisasi->pengajuan_program_kerja_id);
        $this->assertSame('Pelatihan Belum Diajukan', $realisasi->name);
        $this->assertSame(60, $realisasi->persentase_ketercapaian);
        $this->assertSame(EnumJenisRealisasi::TanpaAnggaran, $realisasi->jenis_realisasi);

        @unlink($path);
    }

    public function test_beberapa_baris_program_kerja_sama_memakai_satu_pengajuan(): void
    {
        $penawaran = $this->seedPenawaran($this->unitA, 'Pelatihan Belum Diajukan');
        $kode = KodeReferensiProgramKerja::daftarProgramKerja($penawaran->id);

        $path = $this->tulisCsv(
            "{$kode},2026-04-01,2026-04-02,60,\"Angkatan pertama.\"\n"
            ."{$kode},2026-05-01,2026-05-02,80,\"Angkatan kedua.\"\n",
        );

        $result = $this->impor($path);

        $this->assertFalse($result->failed(), implode(' ', $result->errors));
        $this->assertSame(2, $result->imported);
        $this->assertSame(1, PengajuanProgramKerja::query()->count());
        $this->assertSame(2, RealisasiProgramKerja::query()->count());

        @unlink($path);
    }

    public function test_kode_daftar_program_kerja_memakai_pengajuan_yang_sudah_ada(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 'Workshop Penulisan');
        $kode = KodeReferensiProgramKerja::daftarProgramKerja($pengajuan->penawaran_program_kerja_id);

        $path = $this->tulisCsv("{$kode},2026-04-01,,75,\"Workshop terlaksana.\"\n");

        $result = $this->impor($path);

        $this->assertFalse($result->failed(), implode(' ', $result->errors));
        $this->assertSame(1, PengajuanProgramKerja::query()->count());
        $this->assertSame(
            $pengajuan->id,
            RealisasiProgramKerja::query()->firstOrFail()->pengajuan_program_kerja_id,
        );

        @unlink($path);
    }

    public function test_kode_daftar_program_kerja_dengan_pengajuan_kembar_ditolak(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 'Workshop Penulisan');

        $kembar = PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $pengajuan->penawaran_program_kerja_id,
            'unit_kerja_id' => $this->unitA->id,
            'alokasi_anggaran' => 5_000_000,
            'status' => EnumStatusPengajuan::Diterima,
        ]);

        $kode = KodeReferensiProgramKerja::daftarProgramKerja($pengajuan->penawaran_program_kerja_id);
        $path = $this->tulisCsv("{$kode},2026-04-01,,75,\"Workshop terlaksana.\"\n");

        $result = $this->impor($path);

        $this->assertTrue($result->failed());
        $this->assertStringContainsString('lebih dari satu pengajuan', implode(' ', $result->errors));
        $this->assertStringContainsString((string) $kembar->id, implode(' ', $result->errors));
        $this->assertSame(0, RealisasiProgramKerja::query()->count());

        @unlink($path);
    }

    public function test_kode_daftar_program_kerja_di_luar_cakupan_membatalkan_impor(): void
    {
        $penawaran = $this->seedPenawaran($this->unitB, 'Pelatihan Unit B');

        $kode = KodeReferensiProgramKerja::daftarProgramKerja($penawaran->id);
        $path = $this->tulisCsv("{$kode},2026-04-01,,60,\"Pelatihan terlaksana.\"\n");

        $result = $this->impor($path, unitKerjaIds: [$this->unitA->id]);

        $this->assertTrue($result->failed());
        $this->assertStringContainsString('Daftar Program Kerja', implode(' ', $result->errors));
        $this->assertSame(0, PengajuanProgramKerja::query()->count());
        $this->assertSame(0, RealisasiProgramKerja::query()->count());

        @unlink($path);
    }

    public function test_lembar_referensi_dari_pengajuan_mengabaikan_yang_belum_diajukan(): void
    {
        $this->seedPengajuan($this->unitA, 'Workshop Penulisan');
        $this->seedPenawaran($this->unitA, 'Pelatihan Belum Diajukan');

        $lembar = $this->bacaTemplate(
            unitKerjaIds: [$this->unitA->id],
            sumber: EnumSumberReferensiProgramKerja::Pengajuan,
        );

        $referensi = $this->rataTeks($lembar['Referensi Program Kerja']);

        $this->assertStringContainsString('Workshop Penulisan', $referensi);
        $this->assertStringNotContainsString('Pelatihan Belum Diajukan', $referensi);
        $this->assertStringContainsString('Pengajuan Program Kerja', $referensi);
    }

    public function test_sumber_referensi_asing_kembali_ke_bawaannya(): void
    {
        $this->seedPengajuan($this->unitA, 'Workshop Penulisan');
        $this->seedPenawaran($this->unitA, 'Pelatihan Belum Diajukan');

        $response = (new CapaianProgramKerjasImport)
            ->withContext([
                'tahun_kerja_id' => $this->tahunKerja->id,
                'unit_kerja_ids' => [$this->unitA->id],
                'sumber_referensi' => 'entah-apa',
            ])
            ->downloadTemplate();

        @unlink($response->getFile()->getPathname());

        $this->assertSame(
            EnumSumberReferensiProgramKerja::Pengajuan,
            EnumSumberReferensiProgramKerja::dariNilai('entah-apa'),
        );
    }

    public function test_modal_impor_menawarkan_pilihan_sumber_referensi(): void
    {
        $this->seedPengajuan($this->unitA, 'Workshop Penulisan');

        Livewire::test(MonitoringProgramKerja::class)
            ->mountAction(ImporCapaianProgramKerjaAction::getDefaultName())
            ->assertSchemaStateSet([
                'sumber_referensi' => EnumSumberReferensiProgramKerja::Pengajuan,
            ]);
    }

    /**
     * Tombol "Unduh Template" berada di dalam modal impor, jadi yang diuji di sini
     * pilihan sumber pada modal itu benar-benar sampai ke berkas yang terunduh.
     */
    public function test_unduh_template_dari_modal_mengikuti_pilihan_sumber_referensi(): void
    {
        $this->seedPengajuan($this->unitA, 'Workshop Penulisan');
        $this->seedPenawaran($this->unitA, 'Pelatihan Belum Diajukan');

        $komponen = Livewire::test(MonitoringProgramKerja::class)
            ->mountAction(ImporCapaianProgramKerjaAction::getDefaultName())
            ->fillForm(['sumber_referensi' => EnumSumberReferensiProgramKerja::DaftarProgramKerja->value])
            ->callAction(TestAction::make('downloadTemplate')->schemaComponent('file'))
            ->assertHasNoErrors()
            ->assertFileDownloaded();

        // Berkasnya benar-benar disusun dari katalog Daftar Program Kerja, bukan
        // sekadar terunduh: program kerja yang belum diajukan ikut terdaftar.
        $referensi = $this->rataTeks($this->bacaUnduhan($komponen)['Referensi Program Kerja']);

        $this->assertStringContainsString('Pelatihan Belum Diajukan', $referensi);
        $this->assertStringContainsString('Belum diajukan', $referensi);
    }

    /**
     * Isi berkas yang baru saja diunduh komponen Livewire, per lembar.
     *
     * @return array<string, list<list<mixed>>>
     */
    private function bacaUnduhan(Testable $komponen): array
    {
        $isi = base64_decode((string) data_get($komponen->effects, 'download.content'));
        $path = tempnam(sys_get_temp_dir(), 'unduhan_').'.xlsx';
        file_put_contents($path, $isi);

        $lembar = $this->bacaLembar($path);

        @unlink($path);

        return $lembar;
    }

    public function test_tombol_impor_capaian_ada_di_kepala_halaman_dan_mengikuti_hak_aksesnya(): void
    {
        $this->seedPengajuan($this->unitA, 'Workshop Penulisan');

        $pemantau = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);
        $pemantau->givePermissionTo(Permission::findOrCreate('view_page_monitoring_program_kerja', 'web'));

        $this->actingAs($pemantau);

        Livewire::test(MonitoringProgramKerja::class)
            ->assertActionHidden(TestAction::make(ImporCapaianProgramKerjaAction::getDefaultName()));

        $pemantau->givePermissionTo(Permission::findOrCreate(MonitoringProgramKerja::PERMISSION_CATAT_CAPAIAN, 'web'));

        Livewire::test(MonitoringProgramKerja::class)
            ->assertActionVisible(TestAction::make(ImporCapaianProgramKerjaAction::getDefaultName()));
    }

    /**
     * Menjalankan impor pada cakupan tahun kerja berjalan; bawaannya kedua unit kerja
     * boleh diakses supaya pengujian scope-lah yang mempersempitnya.
     *
     * @param  array<int, int>|null  $unitKerjaIds
     */
    private function impor(string $path, ?array $unitKerjaIds = null): ImportResult
    {
        return (new CapaianProgramKerjasImport)
            ->withContext([
                'tahun_kerja_id' => $this->tahunKerja->id,
                'unit_kerja_ids' => $unitKerjaIds ?? [$this->unitA->id, $this->unitB->id],
            ])
            ->import($path);
    }

    /**
     * Berkas .csv berisi baris kepala tabel milik impor ini, disusul isian yang diuji.
     */
    private function tulisCsv(string $isi): string
    {
        $path = tempnam(sys_get_temp_dir(), 'impor_capaian_').'.csv';

        file_put_contents(
            $path,
            "kode_program_kerja,tanggal_mulai,tanggal_selesai,persentase_ketercapaian,deskripsi_kegiatan\n".$isi,
        );

        return $path;
    }

    /**
     * Berkas .csv yang ikut membawa kolom nominal, dipakai menguji realisasi
     * beranggaran hasil impor.
     */
    private function tulisCsvBernominal(string $isi): string
    {
        $path = tempnam(sys_get_temp_dir(), 'impor_capaian_').'.csv';

        file_put_contents(
            $path,
            "kode_program_kerja,tanggal_mulai,tanggal_selesai,nominal_digunakan,persentase_ketercapaian,deskripsi_kegiatan\n".$isi,
        );

        return $path;
    }

    private function seedPagu(UnitKerja $unit, float $nominal): PaguAnggaran
    {
        return PaguAnggaran::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $unit->id,
            'amount' => $nominal,
        ]);
    }

    /**
     * Isi berkas template per lembar: nama lembar => seluruh barisnya. Sumber lembar
     * referensi mengikuti pilihan pengguna; null berarti memakai bawaannya.
     *
     * @param  array<int, int>  $unitKerjaIds
     * @return array<string, list<list<mixed>>>
     */
    private function bacaTemplate(array $unitKerjaIds, ?EnumSumberReferensiProgramKerja $sumber = null): array
    {
        $response = (new CapaianProgramKerjasImport)
            ->withContext([
                'tahun_kerja_id' => $this->tahunKerja->id,
                'nama_tahun_kerja' => $this->tahunKerja->name,
                'unit_kerja_ids' => $unitKerjaIds,
                'sumber_referensi' => $sumber?->value,
            ])
            ->downloadTemplate();

        $lembar = $this->bacaLembar($response->getFile()->getPathname());

        @unlink($response->getFile()->getPathname());

        return $lembar;
    }

    /**
     * Seluruh lembar sebuah berkas .xlsx: nama lembar => barisnya.
     *
     * @return array<string, list<list<mixed>>>
     */
    private function bacaLembar(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);

        $lembar = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $baris = [];

            foreach ($sheet->getRowIterator() as $row) {
                $baris[] = $row->toArray();
            }

            $lembar[$sheet->getName()] = $baris;
        }

        $reader->close();

        return $lembar;
    }

    /**
     * @param  list<list<mixed>>  $baris
     * @return list<mixed>|null
     */
    private function cariBaris(array $baris, string $sel): ?array
    {
        foreach ($baris as $isi) {
            if (in_array($sel, array_map(fn (mixed $nilai): string => (string) $nilai, $isi), true)) {
                return $isi;
            }
        }

        return null;
    }

    /**
     * @param  list<list<mixed>>  $baris
     */
    private function rataTeks(array $baris): string
    {
        return implode(' | ', array_map(
            fn (array $isi): string => implode(' ', array_map(fn (mixed $nilai): string => (string) $nilai, $isi)),
            $baris,
        ));
    }

    /**
     * Program kerja yang ditawarkan ke unit kerja namun belum diajukan sama sekali.
     */
    private function seedPenawaran(UnitKerja $unit, string $nama, ?TahunKerja $tahunKerja = null): PenawaranProgramKerja
    {
        return PenawaranProgramKerja::create([
            'tahun_kerja_id' => ($tahunKerja ?? $this->tahunKerja)->id,
            'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::firstOrCreate(['code' => 'B1'], ['name' => 'Akademik'])->id,
            'kategori_id' => Kategori::firstOrCreate(['code' => 'K1'], ['name' => 'Pendidikan'])->id,
            'program_id' => Program::firstOrCreate(['name' => 'Tridharma'])->id,
            'name' => $nama,
            'is_active' => true,
        ]);
    }

    private function seedPengajuan(UnitKerja $unit, string $nama, ?TahunKerja $tahunKerja = null): PengajuanProgramKerja
    {
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => ($tahunKerja ?? $this->tahunKerja)->id,
            'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::firstOrCreate(['code' => 'B1'], ['name' => 'Akademik'])->id,
            'kategori_id' => Kategori::firstOrCreate(['code' => 'K1'], ['name' => 'Pendidikan'])->id,
            'program_id' => Program::firstOrCreate(['name' => 'Tridharma'])->id,
            'name' => $nama,
            'is_active' => true,
        ]);

        return PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $unit->id,
            'alokasi_anggaran' => 8_000_000,
            'status' => EnumStatusPengajuan::Diterima,
        ]);
    }
}
