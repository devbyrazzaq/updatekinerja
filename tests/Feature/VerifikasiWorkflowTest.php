<?php

namespace Tests\Feature;

use App\Enums\EnumMetodePembayaran;
use App\Enums\EnumStatusPencairan;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Actions\TerimaLaporanRealisasiAction;
use App\Filament\Resources\VerifikasiBiroKeuangans\Pages\ListVerifikasiBiroKeuangans;
use App\Filament\Resources\VerifikasiLaporans\Pages\ListVerifikasiLaporans;
use App\Filament\Resources\VerifikasiPengajuans\Pages\ListVerifikasiPengajuans;
use App\Filament\Resources\VerifikasiRektors\Pages\ViewVerifikasiRektor;
use App\Filament\Resources\VerifikasiRektors\VerifikasiRektorResource;
use App\Filament\Resources\VerifikasiWakilRektors\Pages\ViewVerifikasiWakilRektor;
use App\Models\Bank;
use App\Models\Bidang;
use App\Models\JadwalPencairan;
use App\Models\Kategori;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\RealisasiProgramKerja;
use App\Models\RekeningBank;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VerifikasiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_full_workflow_from_pengajuan_to_selesai(): void
    {
        $periode = Periode::create(['name' => 'P1', 'start_datetime' => now(), 'end_datetime' => now()->addYear()]);
        $tahunKerja = TahunKerja::create(['periode_id' => $periode->id, 'name' => 'TA 2026', 'start_datetime' => now(), 'end_datetime' => now()->addYear(), 'status' => EnumStatusTahunKerja::Berjalan]);
        $unit = UnitKerja::create(['name' => 'Fakultas Teknik']);
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $tahunKerja->id,
            'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::create(['code' => 'B1', 'name' => 'Pendidikan'])->id,
            'kategori_id' => Kategori::create(['code' => 'K1', 'name' => 'Rutin'])->id,
            'program_id' => Program::create(['name' => 'Tridharma'])->id,
            'name' => 'Workshop',
            'is_active' => true,
        ]);

        // 1. Pengajuan diajukan
        $pengajuan = PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $unit->id,
            'alokasi_anggaran' => 20000000,
            'status' => EnumStatusPengajuan::Diajukan,
        ]);

        // 2. Verifikasi Pengajuan (Tahap 1) -> Diterima
        Livewire::test(ListVerifikasiPengajuans::class)
            ->assertTableActionVisible('verifikasi', $pengajuan)
            ->callAction(TestAction::make('verifikasi')->table($pengajuan), ['hasil' => 'setuju'])
            ->assertHasNoActionErrors();
        $this->assertSame(EnumStatusPengajuan::Diterima, $pengajuan->refresh()->status);

        // 3. Realisasi dibuat & diajukan
        $realisasi = RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'name' => 'Pelaksanaan Workshop',
            'anggaran_digunakan' => 18000000,
            'status' => EnumStatusRealisasi::Diajukan,
        ]);

        // 4. Verifikasi Rektor (lewati penetapan nominal) -> VerifikasiWakil
        Livewire::test(ViewVerifikasiRektor::class, ['record' => $realisasi->getRouteKey()])
            ->callAction('setujui', ['mode' => 'lewati'])
            ->assertHasNoActionErrors();
        $this->assertSame(EnumStatusRealisasi::VerifikasiWakil, $realisasi->refresh()->status);

        // 5. Verifikasi Wakil Rektor (tetapkan nominal) -> VerifikasiKeuangan
        Livewire::test(ViewVerifikasiWakilRektor::class, ['record' => $realisasi->getRouteKey()])
            ->callAction('setujui', ['mode' => 'tentukan', 'nominal' => 17000000])
            ->assertHasNoActionErrors();
        $realisasi->refresh();
        $this->assertSame(EnumStatusRealisasi::VerifikasiKeuangan, $realisasi->status);
        $this->assertSame('17000000.00', $realisasi->nominal_disetujui);

        // 6a. Biro Keuangan menjadwalkan pencairan ke sebuah jadwal -> Dijadwalkan
        //     (Menunggu Anggaran Diberikan)
        $jadwal = JadwalPencairan::create([
            'tahun_kerja_id' => $tahunKerja->id,
            'name' => 'Pencairan Awal Bulan Januari',
            'tanggal_pencairan' => now()->addWeek()->toDateString(),
            'status' => EnumStatusPencairan::Dijadwalkan,
        ]);

        // Rekening utama unit kerja: terpilih otomatis pada modal penjadwalan.
        $rekening = RekeningBank::create([
            'bank_id' => Bank::create(['name' => 'Bank Syariah Indonesia', 'code' => '451', 'is_active' => true])->id,
            'unit_kerja_id' => $unit->id,
            'nomor_rekening' => '1234567890',
            'atas_nama' => 'Fakultas Teknik',
            'is_utama' => true,
            'is_active' => true,
        ]);

        Livewire::test(ListVerifikasiBiroKeuangans::class)
            ->callAction(TestAction::make('prosesPencairan')->table($realisasi), [
                'jadwal_pencairan_id' => $jadwal->id,
            ]);
        $realisasi->refresh();
        $this->assertSame(EnumStatusRealisasi::Dijadwalkan, $realisasi->status);
        $this->assertSame($jadwal->id, $realisasi->jadwal_pencairan_id);
        $this->assertSame($rekening->id, $realisasi->rekening_bank_id);

        // 6b. Anggaran ditandai dicairkan (transfer ke rekening) -> MenungguLaporan
        Livewire::test(ListVerifikasiBiroKeuangans::class)
            ->callAction(TestAction::make('tandaiDicairkan')->table($realisasi));
        $realisasi->refresh();
        $this->assertSame(EnumStatusRealisasi::MenungguLaporan, $realisasi->status);
        $this->assertSame(EnumMetodePembayaran::Transfer, $realisasi->metode_pembayaran);
        $this->assertSame($rekening->id, $realisasi->rekening_bank_id);
        $this->assertSame(EnumStatusPencairan::Dicairkan, $jadwal->refresh()->status);

        // 7. Simulasi unit mengunggah laporan -> VerifikasiLaporan
        $realisasi->update(['laporan_path' => 'laporan-realisasi/dummy.pdf', 'laporan_diserahkan_at' => now(), 'status' => EnumStatusRealisasi::VerifikasiLaporan]);

        // 8. Verifikasi Laporan diterima dari modal detail -> Selesai
        Livewire::test(ListVerifikasiLaporans::class)
            ->callAction([
                TestAction::make('view')->table($realisasi),
                TestAction::make(TerimaLaporanRealisasiAction::class),
            ])
            ->assertHasNoActionErrors();
        $this->assertSame(EnumStatusRealisasi::Selesai, $realisasi->refresh()->status);
        $this->assertNotNull($realisasi->laporan_disetujui_at);
    }

    public function test_view_verifikasi_rektor_menampilkan_pengaju_dan_persetujuan_anggaran(): void
    {
        $periode = Periode::create(['name' => 'P1', 'start_datetime' => now(), 'end_datetime' => now()->addYear()]);
        $tahunKerja = TahunKerja::create(['periode_id' => $periode->id, 'name' => 'TA 2026', 'start_datetime' => now(), 'end_datetime' => now()->addYear(), 'status' => EnumStatusTahunKerja::Berjalan]);
        $unit = UnitKerja::create(['name' => 'Unit']);
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $tahunKerja->id, 'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::create(['code' => 'B1', 'name' => 'B'])->id,
            'kategori_id' => Kategori::create(['code' => 'K1', 'name' => 'K'])->id,
            'program_id' => Program::create(['name' => 'P'])->id,
            'name' => 'X', 'is_active' => true,
        ]);
        $pengaju = User::factory()->create(['name' => 'Budi Pengaju']);
        $pengajuan = PengajuanProgramKerja::create(['penawaran_program_kerja_id' => $penawaran->id, 'unit_kerja_id' => $unit->id, 'user_id' => $pengaju->id, 'alokasi_anggaran' => 20000000, 'status' => EnumStatusPengajuan::Diterima]);
        $realisasi = RealisasiProgramKerja::create(['pengajuan_program_kerja_id' => $pengajuan->id, 'name' => 'Pelaksanaan', 'anggaran_digunakan' => 15000000, 'status' => EnumStatusRealisasi::Diajukan]);

        Livewire::test(ViewVerifikasiRektor::class, ['record' => $realisasi->getRouteKey()])
            ->assertSee('Detail Pengaju')
            ->assertSee('Budi Pengaju')
            ->assertSee('Persentase Anggaran')
            ->assertSee('Persetujuan Anggaran')
            ->assertActionVisible('setujui')
            ->callAction('setujui', ['mode' => 'tentukan', 'nominal' => 14000000])
            ->assertHasNoActionErrors()
            ->assertRedirect(VerifikasiRektorResource::getUrl('index'));

        $realisasi->refresh();
        $this->assertSame(EnumStatusRealisasi::VerifikasiWakil, $realisasi->status);
        $this->assertSame('14000000.00', $realisasi->nominal_disetujui);
        $this->assertSame(auth()->id(), $realisasi->rektor_id);
    }

    public function test_setujui_wakil_rektor_tanpa_opsi_lewati(): void
    {
        $periode = Periode::create(['name' => 'P1', 'start_datetime' => now(), 'end_datetime' => now()->addYear()]);
        $tahunKerja = TahunKerja::create(['periode_id' => $periode->id, 'name' => 'TA 2026', 'start_datetime' => now(), 'end_datetime' => now()->addYear(), 'status' => EnumStatusTahunKerja::Berjalan]);
        $unit = UnitKerja::create(['name' => 'Unit']);
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $tahunKerja->id, 'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::create(['code' => 'B1', 'name' => 'B'])->id,
            'kategori_id' => Kategori::create(['code' => 'K1', 'name' => 'K'])->id,
            'program_id' => Program::create(['name' => 'P'])->id,
            'name' => 'X', 'is_active' => true,
        ]);
        $pengajuan = PengajuanProgramKerja::create(['penawaran_program_kerja_id' => $penawaran->id, 'unit_kerja_id' => $unit->id, 'alokasi_anggaran' => 20000000, 'status' => EnumStatusPengajuan::Diterima]);
        $realisasi = RealisasiProgramKerja::create(['pengajuan_program_kerja_id' => $pengajuan->id, 'name' => 'Pelaksanaan', 'anggaran_digunakan' => 12000000, 'status' => EnumStatusRealisasi::VerifikasiWakil]);

        $component = Livewire::test(ViewVerifikasiWakilRektor::class, ['record' => $realisasi->getRouteKey()]);

        // Wakil Rektor tidak memiliki opsi "lewati": nilai tersebut ditolak validasi.
        $component->mountAction('setujui')
            ->setActionData(['mode' => 'lewati'])
            ->callMountedAction()
            ->assertHasActionErrors(['mode']);

        $this->assertSame(EnumStatusRealisasi::VerifikasiWakil, $realisasi->refresh()->status);

        // Menyetujui sesuai anggaran menetapkan nominal sebesar yang diajukan.
        $component->mountAction('setujui')
            ->setActionData(['mode' => 'sesuai'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $realisasi->refresh();
        $this->assertSame(EnumStatusRealisasi::VerifikasiKeuangan, $realisasi->status);
        $this->assertSame('12000000.00', $realisasi->nominal_disetujui);
        $this->assertSame(auth()->id(), $realisasi->wakil_id);
    }

    public function test_rektor_meminta_revisi_mengembalikan_realisasi_ke_status_revisi(): void
    {
        $periode = Periode::create(['name' => 'P1', 'start_datetime' => now(), 'end_datetime' => now()->addYear()]);
        $tahunKerja = TahunKerja::create(['periode_id' => $periode->id, 'name' => 'TA 2026', 'start_datetime' => now(), 'end_datetime' => now()->addYear(), 'status' => EnumStatusTahunKerja::Berjalan]);
        $unit = UnitKerja::create(['name' => 'Unit']);
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $tahunKerja->id, 'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::create(['code' => 'B1', 'name' => 'B'])->id,
            'kategori_id' => Kategori::create(['code' => 'K1', 'name' => 'K'])->id,
            'program_id' => Program::create(['name' => 'P'])->id,
            'name' => 'X', 'is_active' => true,
        ]);
        $pengaju = User::factory()->create();
        $pengajuan = PengajuanProgramKerja::create(['penawaran_program_kerja_id' => $penawaran->id, 'unit_kerja_id' => $unit->id, 'user_id' => $pengaju->id, 'alokasi_anggaran' => 20000000, 'status' => EnumStatusPengajuan::Diterima]);
        $realisasi = RealisasiProgramKerja::create(['pengajuan_program_kerja_id' => $pengajuan->id, 'name' => 'Pelaksanaan', 'anggaran_digunakan' => 15000000, 'status' => EnumStatusRealisasi::Diajukan]);

        Livewire::test(ViewVerifikasiRektor::class, ['record' => $realisasi->getRouteKey()])
            ->assertActionVisible('revisi')
            ->callAction('revisi', ['catatan' => '<p>Perbaiki rincian anggaran.</p>'])
            ->assertHasNoActionErrors()
            ->assertRedirect(VerifikasiRektorResource::getUrl('index'));

        $realisasi->refresh();
        $this->assertSame(EnumStatusRealisasi::Revisi, $realisasi->status);
        $this->assertSame('<p>Perbaiki rincian anggaran.</p>', $realisasi->catatan_verifikasi);
        $this->assertSame(auth()->id(), $realisasi->rektor_id);
        $this->assertNotNull($realisasi->logs()->where('properties->catatan', '<p>Perbaiki rincian anggaran.</p>')->first());
    }

    public function test_nominal_final_rektor_tidak_dapat_diubah_wakil_rektor(): void
    {
        $periode = Periode::create(['name' => 'P1', 'start_datetime' => now(), 'end_datetime' => now()->addYear()]);
        $tahunKerja = TahunKerja::create(['periode_id' => $periode->id, 'name' => 'TA 2026', 'start_datetime' => now(), 'end_datetime' => now()->addYear(), 'status' => EnumStatusTahunKerja::Berjalan]);
        $unit = UnitKerja::create(['name' => 'Unit']);
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $tahunKerja->id, 'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::create(['code' => 'B1', 'name' => 'B'])->id,
            'kategori_id' => Kategori::create(['code' => 'K1', 'name' => 'K'])->id,
            'program_id' => Program::create(['name' => 'P'])->id,
            'name' => 'X', 'is_active' => true,
        ]);
        $pengajuan = PengajuanProgramKerja::create(['penawaran_program_kerja_id' => $penawaran->id, 'unit_kerja_id' => $unit->id, 'alokasi_anggaran' => 20000000, 'status' => EnumStatusPengajuan::Diterima]);

        // Rektor sudah menetapkan nominal final; realisasi berada di tahap Wakil Rektor.
        $realisasi = RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'name' => 'Pelaksanaan',
            'anggaran_digunakan' => 12000000,
            'nominal_disetujui' => 15000000,
            'status' => EnumStatusRealisasi::VerifikasiWakil,
        ]);

        // Wakil Rektor hanya meneruskan: nominal final Rektor dipakai, upaya mengubah diabaikan.
        Livewire::test(ViewVerifikasiWakilRektor::class, ['record' => $realisasi->getRouteKey()])
            ->mountAction('setujui')
            ->setActionData(['mode' => 'tentukan', 'nominal' => 9000000])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $realisasi->refresh();
        $this->assertSame(EnumStatusRealisasi::VerifikasiKeuangan, $realisasi->status);
        $this->assertSame('15000000.00', $realisasi->nominal_disetujui);
        $this->assertSame(auth()->id(), $realisasi->wakil_id);
    }

    public function test_keuangan_requires_jadwal_pencairan(): void
    {
        $periode = Periode::create(['name' => 'P1', 'start_datetime' => now(), 'end_datetime' => now()->addYear()]);
        $tahunKerja = TahunKerja::create(['periode_id' => $periode->id, 'name' => 'TA 2026', 'start_datetime' => now(), 'end_datetime' => now()->addYear(), 'status' => EnumStatusTahunKerja::Berjalan]);
        $unit = UnitKerja::create(['name' => 'Unit']);
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $tahunKerja->id, 'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::create(['code' => 'B1', 'name' => 'B'])->id,
            'kategori_id' => Kategori::create(['code' => 'K1', 'name' => 'K'])->id,
            'program_id' => Program::create(['name' => 'P'])->id,
            'name' => 'X', 'is_active' => true,
        ]);
        $pengajuan = PengajuanProgramKerja::create(['penawaran_program_kerja_id' => $penawaran->id, 'unit_kerja_id' => $unit->id, 'alokasi_anggaran' => 1000, 'status' => EnumStatusPengajuan::Diterima]);
        $realisasi = RealisasiProgramKerja::create(['pengajuan_program_kerja_id' => $pengajuan->id, 'name' => 'R', 'anggaran_digunakan' => 500, 'status' => EnumStatusRealisasi::VerifikasiKeuangan]);

        Livewire::test(ListVerifikasiBiroKeuangans::class)
            ->callAction(TestAction::make('prosesPencairan')->table($realisasi), [
                // jadwal_pencairan_id sengaja dikosongkan
            ])
            ->assertHasActionErrors(['jadwal_pencairan_id']);
    }
}
