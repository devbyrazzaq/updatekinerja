<?php

namespace Tests\Feature;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Actions\CatatCapaianProgramKerjaAction;
use App\Filament\Pages\MonitoringProgramKerja;
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
use App\Services\MonitoringAnggaran;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pencatatan capaian program kerja langsung dari halaman Monitoring Program Kerja:
 * tercatat sebagai realisasi tuntas tanpa anggaran, lengkap dengan laporannya.
 */
class CatatCapaianMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private TahunKerja $tahunKerja;

    private UnitKerja $unitA;

    private UnitKerja $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));

        $periode = Periode::create([
            'name' => 'P1',
            'start_datetime' => Carbon::create(2026, 1, 1),
            'end_datetime' => Carbon::create(2026, 12, 31),
        ]);

        $this->tahunKerja = TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA 2026',
            'start_datetime' => Carbon::create(2026, 1, 1),
            'end_datetime' => Carbon::create(2026, 12, 31),
            'status' => EnumStatusTahunKerja::Berjalan,
        ]);

        $this->unitA = UnitKerja::create(['name' => 'Unit A']);
        $this->unitB = UnitKerja::create(['name' => 'Unit B']);

        PaguAnggaran::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $this->unitA->id,
            'amount' => 20_000_000,
        ]);

        $user = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);
        $user->givePermissionTo(Permission::findOrCreate('bypass_data_scope', 'web'));

        $this->actingAs($user);
    }

    public function test_capaian_tercatat_sebagai_realisasi_selesai_tanpa_anggaran(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 'Workshop Penulisan');

        Livewire::test(MonitoringProgramKerja::class)
            ->callAction(TestAction::make(CatatCapaianProgramKerjaAction::getDefaultName())->table(), [
                'unit_kerja_id' => $this->unitA->id,
                'pengajuan_program_kerja_id' => $pengajuan->id,
                'jenis_pelaksanaan' => 'satu_hari',
                'tanggal_mulai' => '2026-03-10',
                'deskripsi_kegiatan' => '<p>Workshop terlaksana dua sesi.</p>',
                'laporan_path' => [UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf')],
                'persentase_ketercapaian' => 70,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $realisasi = RealisasiProgramKerja::query()->firstOrFail();

        $this->assertSame(EnumStatusRealisasi::Selesai, $realisasi->status);
        $this->assertSame(70, $realisasi->persentase_ketercapaian);
        $this->assertSame(0.0, (float) $realisasi->anggaran_digunakan);
        $this->assertNull($realisasi->dicairkan_at);
        $this->assertNotNull($realisasi->laporan_disetujui_at);
        $this->assertCount(1, (array) $realisasi->laporan_path);
        $this->assertSame('2026-03-10', $realisasi->start_datetime->toDateString());
        // Satu tanggal berarti mulai dan selesai pada hari yang sama.
        $this->assertSame('2026-03-10', $realisasi->end_datetime->toDateString());

        // Laporannya ikut tersimpan sebagai dokumen realisasi agar dapat dipratinjau.
        $this->assertSame(1, $realisasi->laporans()->count());

        $ringkasan = MonitoringAnggaran::untukUnit($this->unitA->id, $this->tahunKerja)->ringkasan();

        $this->assertSame(70.0, $ringkasan->capaian);
        // Capaian tanpa anggaran tidak menggeser penyerapan sama sekali.
        $this->assertSame(0.0, $ringkasan->terserap());
        $this->assertSame(0.0, $ringkasan->komitmen);
    }

    public function test_rentang_waktu_pelaksanaan_disimpan_apa_adanya(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 'Pelatihan Berkala');

        Livewire::test(MonitoringProgramKerja::class)
            ->callAction(TestAction::make(CatatCapaianProgramKerjaAction::getDefaultName())->table(), [
                'unit_kerja_id' => $this->unitA->id,
                'pengajuan_program_kerja_id' => $pengajuan->id,
                'jenis_pelaksanaan' => 'rentang',
                'tanggal_mulai' => '2026-04-01',
                'tanggal_selesai' => '2026-04-05',
                'deskripsi_kegiatan' => '<p>Pelatihan lima hari.</p>',
                'laporan_path' => [UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf')],
                'persentase_ketercapaian' => 100,
            ])
            ->assertHasNoActionErrors();

        $realisasi = RealisasiProgramKerja::query()->firstOrFail();

        $this->assertSame('2026-04-01', $realisasi->start_datetime->toDateString());
        $this->assertSame('2026-04-05', $realisasi->end_datetime->toDateString());
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

        Livewire::test(MonitoringProgramKerja::class)
            ->callAction(TestAction::make(CatatCapaianProgramKerjaAction::getDefaultName())->table(), [
                'unit_kerja_id' => $this->unitA->id,
                'pengajuan_program_kerja_id' => $pengajuan->id,
                'jenis_pelaksanaan' => 'satu_hari',
                'tanggal_mulai' => '2026-05-01',
                'deskripsi_kegiatan' => '<p>Lanjutan kegiatan.</p>',
                'laporan_path' => [UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf')],
                'persentase_ketercapaian' => 50,
            ])
            ->assertHasActionErrors(['persentase_ketercapaian' => 'min']);

        $this->assertSame(1, RealisasiProgramKerja::query()->count());
    }

    public function test_program_kerja_di_luar_scope_pengguna_ditolak(): void
    {
        $pengajuanLuar = $this->seedPengajuan($this->unitB, 'Kegiatan Unit B');

        $pengguna = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);
        $pengguna->givePermissionTo(Permission::findOrCreate('view_page_monitoring_program_kerja', 'web'));
        $pengguna->givePermissionTo(Permission::findOrCreate(MonitoringProgramKerja::PERMISSION_CATAT_CAPAIAN, 'web'));

        $this->actingAs($pengguna);

        Livewire::test(MonitoringProgramKerja::class)
            ->callAction(TestAction::make(CatatCapaianProgramKerjaAction::getDefaultName())->table(), [
                'unit_kerja_id' => $this->unitB->id,
                'pengajuan_program_kerja_id' => $pengajuanLuar->id,
                'jenis_pelaksanaan' => 'satu_hari',
                'tanggal_mulai' => '2026-05-01',
                'deskripsi_kegiatan' => '<p>Kegiatan unit lain.</p>',
                'laporan_path' => [UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf')],
                'persentase_ketercapaian' => 80,
            ]);

        $this->assertSame(0, RealisasiProgramKerja::query()->count());
    }

    public function test_laporan_capaian_dapat_dipratinjau_dari_baris_tabel(): void
    {
        $pengajuan = $this->seedPengajuan($this->unitA, 'Workshop Penulisan');
        $penawaranId = $pengajuan->penawaran_program_kerja_id;

        Livewire::test(MonitoringProgramKerja::class)
            // Belum ada capaian, jadi tidak ada dokumen yang bisa dipratinjau.
            ->assertActionHidden(TestAction::make('lihatLaporanCapaian')->table($penawaranId))
            ->callAction(TestAction::make(CatatCapaianProgramKerjaAction::getDefaultName())->table(), [
                'unit_kerja_id' => $this->unitA->id,
                'pengajuan_program_kerja_id' => $pengajuan->id,
                'jenis_pelaksanaan' => 'satu_hari',
                'tanggal_mulai' => '2026-03-10',
                'deskripsi_kegiatan' => '<p>Workshop terlaksana.</p>',
                'laporan_path' => [UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf')],
                'persentase_ketercapaian' => 70,
            ])
            ->assertHasNoActionErrors()
            ->assertActionVisible(TestAction::make('lihatLaporanCapaian')->table($penawaranId));
    }

    public function test_tombol_catat_capaian_hanya_untuk_pemilik_hak_aksesnya(): void
    {
        $this->seedPengajuan($this->unitA, 'Workshop Penulisan');

        $pemantau = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);
        $pemantau->givePermissionTo(Permission::findOrCreate('view_page_monitoring_program_kerja', 'web'));

        $this->actingAs($pemantau);

        Livewire::test(MonitoringProgramKerja::class)
            ->assertActionHidden(TestAction::make(CatatCapaianProgramKerjaAction::getDefaultName())->table());

        $pemantau->givePermissionTo(Permission::findOrCreate(MonitoringProgramKerja::PERMISSION_CATAT_CAPAIAN, 'web'));

        Livewire::test(MonitoringProgramKerja::class)
            ->assertActionVisible(TestAction::make(CatatCapaianProgramKerjaAction::getDefaultName())->table());
    }

    public function test_hak_akses_catat_capaian_terdaftar_terpisah_dari_akses_halaman(): void
    {
        $definisi = MonitoringProgramKerja::getPermissionDefinitions();

        $this->assertArrayHasKey('view_page_monitoring_program_kerja', $definisi);
        $this->assertArrayHasKey(MonitoringProgramKerja::PERMISSION_CATAT_CAPAIAN, $definisi);
    }

    private function seedPengajuan(UnitKerja $unit, string $nama): PengajuanProgramKerja
    {
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
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
