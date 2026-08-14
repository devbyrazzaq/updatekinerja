<?php

namespace Tests\Feature;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Resources\PengajuanProgramKerjas\Pages\CreatePengajuanProgramKerja;
use App\Filament\Resources\PengajuanProgramKerjas\Pages\ViewPengajuanProgramKerja;
use App\Filament\Resources\PerencanaanPengajuanProgramKerjas\Pages\CreatePerencanaanPengajuanProgramKerja;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\PaguAnggaran;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PengajuanProgramKerjaFormTest extends TestCase
{
    use RefreshDatabase;

    protected UnitKerja $unit;

    protected TahunKerja $tahunKerja;

    protected function setUp(): void
    {
        parent::setUp();

        $periode = Periode::create([
            'name' => 'Periode 2026',
            'start_datetime' => now(),
            'end_datetime' => now()->addYear(),
        ]);

        $this->tahunKerja = TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA 2026',
            'start_datetime' => now(),
            'end_datetime' => now()->addYear(),
            'status' => EnumStatusTahunKerja::Berjalan,
        ]);

        $this->unit = UnitKerja::create(['name' => 'Fakultas Teknik']);

        $this->actingAs(User::factory()->create(['unit_kerja_id' => $this->unit->id]));
    }

    protected function seedPenawaran(): PenawaranProgramKerja
    {
        return PenawaranProgramKerja::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $this->unit->id,
            'bidang_id' => Bidang::create(['code' => 'B1', 'name' => 'Pendidikan'])->id,
            'kategori_id' => Kategori::create(['code' => 'K1', 'name' => 'Rutin'])->id,
            'program_id' => Program::create(['name' => 'Tridharma'])->id,
            'name' => 'Riset Unggulan',
            'target' => '10 judul',
            'is_active' => true,
        ]);
    }

    public function test_create_page_renders(): void
    {
        Livewire::test(CreatePengajuanProgramKerja::class)->assertOk();
    }

    public function test_ringkasan_anggaran_tampil_dari_awal_tanpa_memilih_program_kerja(): void
    {
        PaguAnggaran::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $this->unit->id,
            'amount' => 10_000_000,
        ]);

        $penawaran = $this->seedPenawaran();

        // Dana dialokasikan mengabaikan pengajuan ditolak: 2jt (diterima) + 1jt (draf) = 3jt.
        foreach ([
            [2_000_000, EnumStatusPengajuan::Diterima],
            [1_000_000, EnumStatusPengajuan::Draft],
            [5_000_000, EnumStatusPengajuan::Ditolak],
        ] as [$alokasi, $status]) {
            PengajuanProgramKerja::create([
                'penawaran_program_kerja_id' => $penawaran->id,
                'unit_kerja_id' => $this->unit->id,
                'alokasi_anggaran' => $alokasi,
                'status' => $status->value,
            ]);
        }

        // Tanpa memilih program kerja, ringkasan sudah tampil mengikuti unit pengguna.
        Livewire::test(CreatePengajuanProgramKerja::class)
            ->assertSee('Ringkasan Anggaran')
            ->assertSee('Rp 10.000.000') // total anggaran (pagu)
            ->assertSee('Rp 3.000.000')  // dana dialokasikan (tanpa yang ditolak)
            ->assertSee('Rp 7.000.000'); // sisa anggaran
    }

    public function test_detail_program_kerja_tampil_setelah_memilih(): void
    {
        $penawaran = $this->seedPenawaran();

        Livewire::test(CreatePengajuanProgramKerja::class)
            ->fillForm(['penawaran_program_kerja_id' => $penawaran->id])
            ->assertSee('Riset Unggulan')
            ->assertSee('10 judul')
            ->assertSee('Belum ada pengajuan untuk program kerja ini.');
    }

    public function test_detail_menampilkan_pengajuan_yang_sudah_ada(): void
    {
        $penawaran = $this->seedPenawaran();

        PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $this->unit->id,
            'alokasi_anggaran' => 2_000_000,
            'status' => EnumStatusPengajuan::Diterima->value,
        ]);

        Livewire::test(CreatePengajuanProgramKerja::class)
            ->fillForm(['penawaran_program_kerja_id' => $penawaran->id])
            ->assertSee('Sudah ada 1 pengajuan untuk program kerja ini.')
            ->assertSee('Fakultas Teknik');
    }

    public function test_program_kerja_hanya_dari_unit_kerja_terpilih(): void
    {
        $penawaranUnitIni = $this->seedPenawaran();

        $unitLain = UnitKerja::create(['name' => 'Fakultas Ekonomi']);
        $penawaranUnitLain = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $unitLain->id,
            'bidang_id' => Bidang::create(['code' => 'B2', 'name' => 'Ekonomi'])->id,
            'kategori_id' => Kategori::create(['code' => 'K2', 'name' => 'Non Rutin'])->id,
            'program_id' => Program::create(['name' => 'Kewirausahaan'])->id,
            'name' => 'Program Unit Lain',
            'is_active' => true,
        ]);

        PaguAnggaran::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $this->unit->id,
            'amount' => 10_000_000,
        ]);

        // Program kerja dari unit lain harus ditolak untuk unit yang dipilih.
        Livewire::test(CreatePengajuanProgramKerja::class)
            ->fillForm([
                'unit_kerja_id' => $this->unit->id,
                'penawaran_program_kerja_id' => $penawaranUnitLain->id,
                'alokasi_anggaran' => 1_000_000,
            ])
            ->call('create')
            ->assertHasFormErrors(['penawaran_program_kerja_id']);

        // Program kerja dari unit yang sama harus lolos.
        Livewire::test(CreatePengajuanProgramKerja::class)
            ->fillForm([
                'unit_kerja_id' => $this->unit->id,
                'penawaran_program_kerja_id' => $penawaranUnitIni->id,
                'alokasi_anggaran' => 1_000_000,
                'deskripsi_kegiatan' => '<p>Rencana kegiatan.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_estimasi_kegiatan_opsional_dapat_disimpan(): void
    {
        PaguAnggaran::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $this->unit->id,
            'amount' => 10_000_000,
        ]);

        $penawaran = $this->seedPenawaran();

        Livewire::test(CreatePengajuanProgramKerja::class)
            ->fillForm([
                'penawaran_program_kerja_id' => $penawaran->id,
                'unit_kerja_id' => $this->unit->id,
                'alokasi_anggaran' => 1_000_000,
                'deskripsi_kegiatan' => '<p>Rencana kegiatan.</p>',
                'estimasi_mulai' => '2026-08-01 09:00:00',
                'estimasi_selesai' => '2026-08-02 17:00:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('pengajuan_program_kerjas', [
            'penawaran_program_kerja_id' => $penawaran->id,
            'estimasi_mulai' => '2026-08-01 09:00:00',
            'estimasi_selesai' => '2026-08-02 17:00:00',
        ]);
    }

    public function test_estimasi_selesai_harus_setelah_mulai(): void
    {
        PaguAnggaran::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $this->unit->id,
            'amount' => 10_000_000,
        ]);

        $penawaran = $this->seedPenawaran();

        $data = [
            'unit_kerja_id' => $this->unit->id,
            'penawaran_program_kerja_id' => $penawaran->id,
            'alokasi_anggaran' => 1_000_000,
            'deskripsi_kegiatan' => '<p>Rencana kegiatan.</p>',
        ];

        // Selesai sebelum mulai harus gagal.
        Livewire::test(CreatePengajuanProgramKerja::class)
            ->fillForm([
                ...$data,
                'estimasi_mulai' => '2026-08-02 09:00:00',
                'estimasi_selesai' => '2026-08-01 09:00:00',
            ])
            ->call('create')
            ->assertHasFormErrors(['estimasi_selesai']);

        // Selesai setelah mulai harus lolos.
        Livewire::test(CreatePengajuanProgramKerja::class)
            ->fillForm([
                ...$data,
                'estimasi_mulai' => '2026-08-01 09:00:00',
                'estimasi_selesai' => '2026-08-02 09:00:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_deskripsi_kegiatan_wajib_diisi(): void
    {
        PaguAnggaran::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $this->unit->id,
            'amount' => 10_000_000,
        ]);

        $penawaran = $this->seedPenawaran();

        Livewire::test(CreatePengajuanProgramKerja::class)
            ->fillForm([
                'unit_kerja_id' => $this->unit->id,
                'penawaran_program_kerja_id' => $penawaran->id,
                'alokasi_anggaran' => 1_000_000,
                'deskripsi_kegiatan' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['deskripsi_kegiatan']);
    }

    public function test_tombol_utama_menyimpan_sebagai_diajukan(): void
    {
        PaguAnggaran::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $this->unit->id,
            'amount' => 10_000_000,
        ]);

        $penawaran = $this->seedPenawaran();

        Livewire::test(CreatePengajuanProgramKerja::class)
            ->fillForm([
                'unit_kerja_id' => $this->unit->id,
                'penawaran_program_kerja_id' => $penawaran->id,
                'alokasi_anggaran' => 1_000_000,
                'deskripsi_kegiatan' => '<p>Rencana kegiatan.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('pengajuan_program_kerjas', [
            'penawaran_program_kerja_id' => $penawaran->id,
            'status' => EnumStatusPengajuan::Diajukan->value,
        ]);
    }

    public function test_simpan_sebagai_draft(): void
    {
        PaguAnggaran::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $this->unit->id,
            'amount' => 10_000_000,
        ]);

        $penawaran = $this->seedPenawaran();

        Livewire::test(CreatePengajuanProgramKerja::class)
            ->fillForm([
                'unit_kerja_id' => $this->unit->id,
                'penawaran_program_kerja_id' => $penawaran->id,
                'alokasi_anggaran' => 1_000_000,
                'deskripsi_kegiatan' => '<p>Rencana kegiatan.</p>',
            ])
            ->call('createAsDraft')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('pengajuan_program_kerjas', [
            'penawaran_program_kerja_id' => $penawaran->id,
            'status' => EnumStatusPengajuan::Draft->value,
        ]);
    }

    public function test_alokasi_melebihi_sisa_anggaran_ditolak(): void
    {
        PaguAnggaran::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $this->unit->id,
            'amount' => 10_000_000,
        ]);

        $penawaran = $this->seedPenawaran();

        PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $this->unit->id,
            'alokasi_anggaran' => 4_000_000,
            'status' => EnumStatusPengajuan::Diterima->value,
        ]);

        // Sisa anggaran = 10jt - 4jt = 6jt. Pengajuan 7jt harus gagal, 6jt harus lolos.
        Livewire::test(CreatePengajuanProgramKerja::class)
            ->fillForm([
                'penawaran_program_kerja_id' => $penawaran->id,
                'unit_kerja_id' => $this->unit->id,
                'alokasi_anggaran' => 7_000_000,
            ])
            ->call('create')
            ->assertHasFormErrors(['alokasi_anggaran']);

        Livewire::test(CreatePengajuanProgramKerja::class)
            ->fillForm([
                'penawaran_program_kerja_id' => $penawaran->id,
                'unit_kerja_id' => $this->unit->id,
                'alokasi_anggaran' => 6_000_000,
                'deskripsi_kegiatan' => '<p>Rencana kegiatan.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    /**
     * Pengajuan tahun mendatang disusun lewat menu grup Perencanaan, dan perhitungan
     * anggarannya wajib mengikuti tahun kerja milik program yang dipilih — bukan tahun
     * kerja yang sedang berjalan. Tanpa itu, pengajuan tahun mendatang akan diukur
     * terhadap pagu tahun berjalan.
     */
    public function test_pengajuan_tahun_perencanaan_diukur_terhadap_pagu_tahunnya_sendiri(): void
    {
        $perencanaan = TahunKerja::create([
            'periode_id' => $this->tahunKerja->periode_id,
            'name' => 'TA 2027',
            'tahun' => 2027,
            'start_datetime' => now()->addYear(),
            'end_datetime' => now()->addYears(2),
            'status' => EnumStatusTahunKerja::Perencanaan,
        ]);

        // Pagu tahun berjalan besar, pagu tahun perencanaan kecil.
        PaguAnggaran::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $this->unit->id,
            'amount' => 100_000_000,
        ]);
        PaguAnggaran::create([
            'tahun_kerja_id' => $perencanaan->id,
            'unit_kerja_id' => $this->unit->id,
            'amount' => 5_000_000,
        ]);

        $penawaranPerencanaan = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $perencanaan->id,
            'unit_kerja_id' => $this->unit->id,
            'bidang_id' => Bidang::create(['code' => 'B2', 'name' => 'Penelitian'])->id,
            'kategori_id' => Kategori::create(['code' => 'K2', 'name' => 'Pengembangan'])->id,
            'program_id' => Program::create(['name' => 'Tridharma 2027'])->id,
            'name' => 'Program 2027',
            'is_active' => true,
        ]);

        // 6jt melebihi pagu 2027 (5jt) walau masih jauh di bawah pagu 2026 (100jt).
        Livewire::test(CreatePerencanaanPengajuanProgramKerja::class)
            ->fillForm([
                'penawaran_program_kerja_id' => $penawaranPerencanaan->id,
                'unit_kerja_id' => $this->unit->id,
                'alokasi_anggaran' => 6_000_000,
            ])
            ->call('create')
            ->assertHasFormErrors(['alokasi_anggaran']);

        Livewire::test(CreatePerencanaanPengajuanProgramKerja::class)
            ->fillForm([
                'penawaran_program_kerja_id' => $penawaranPerencanaan->id,
                'unit_kerja_id' => $this->unit->id,
                'alokasi_anggaran' => 5_000_000,
                'deskripsi_kegiatan' => '<p>Rencana kegiatan 2027.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(PengajuanProgramKerja::class, [
            'penawaran_program_kerja_id' => $penawaranPerencanaan->id,
            'alokasi_anggaran' => 5_000_000,
        ]);
    }

    public function test_log_dibuat_saat_pengajuan_diajukan(): void
    {
        PaguAnggaran::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $this->unit->id,
            'amount' => 10_000_000,
        ]);

        $penawaran = $this->seedPenawaran();

        Livewire::test(CreatePengajuanProgramKerja::class)
            ->fillForm([
                'unit_kerja_id' => $this->unit->id,
                'penawaran_program_kerja_id' => $penawaran->id,
                'alokasi_anggaran' => 1_000_000,
                'deskripsi_kegiatan' => '<p>Rencana kegiatan.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $log = PengajuanProgramKerja::firstOrFail()->logs()->firstOrFail();

        $this->assertEquals(EnumStatusPengajuan::Diajukan, $log->status);
        $this->assertStringContainsString('mengajukan', $log->description);
        $this->assertStringContainsString('Riset Unggulan', $log->description);
        $this->assertEquals(1_000_000.0, $log->properties['nominal']);
    }

    public function test_log_respon_dibuat_saat_verifikasi(): void
    {
        $penawaran = $this->seedPenawaran();

        $pengajuan = PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $this->unit->id,
            'alokasi_anggaran' => 1_000_000,
            'status' => EnumStatusPengajuan::Diajukan->value,
        ]);

        $pengajuan->catatLog(EnumStatusPengajuan::Diterima, auth()->id(), 'Disetujui sepenuhnya.');

        $log = $pengajuan->logs()->firstOrFail();

        $this->assertEquals(EnumStatusPengajuan::Diterima, $log->status);
        $this->assertStringContainsString('menyetujui', $log->description);
        $this->assertStringContainsString('Disetujui sepenuhnya.', $log->description);
    }

    public function test_stepper_tahapan_tampil_di_halaman_view(): void
    {
        $penawaran = $this->seedPenawaran();

        $pengajuan = PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $this->unit->id,
            'alokasi_anggaran' => 1_000_000,
            'status' => EnumStatusPengajuan::Diajukan->value,
        ]);

        $pengajuan->catatLog(EnumStatusPengajuan::Diajukan, auth()->id());

        Livewire::test(ViewPengajuanProgramKerja::class, ['record' => $pengajuan->getRouteKey()])
            ->assertOk()
            ->assertSee('Tahapan Pengajuan')
            ->assertSee('Verifikasi Rektor')
            ->assertSee('Log')
            ->assertSee('mengajukan');
    }

    public function test_aksi_ajukan_muncul_dan_mengubah_draf_menjadi_diajukan(): void
    {
        $penawaran = $this->seedPenawaran();

        $pengajuan = PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $this->unit->id,
            'user_id' => auth()->id(),
            'alokasi_anggaran' => 1_000_000,
            'status' => EnumStatusPengajuan::Draft->value,
        ]);

        Livewire::test(ViewPengajuanProgramKerja::class, ['record' => $pengajuan->getRouteKey()])
            ->assertActionVisible('ajukan')
            ->callAction('ajukan')
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertEquals(EnumStatusPengajuan::Diajukan, $pengajuan->refresh()->status);
        $this->assertStringContainsString('mengajukan', $pengajuan->logs()->latest('id')->first()->description);
    }

    public function test_aksi_ajukan_tersembunyi_jika_bukan_draf(): void
    {
        $penawaran = $this->seedPenawaran();

        $pengajuan = PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $this->unit->id,
            'user_id' => auth()->id(),
            'alokasi_anggaran' => 1_000_000,
            'status' => EnumStatusPengajuan::Diajukan->value,
        ]);

        Livewire::test(ViewPengajuanProgramKerja::class, ['record' => $pengajuan->getRouteKey()])
            ->assertActionHidden('ajukan');
    }

    public function test_stepper_tahapan_tampil_untuk_draf(): void
    {
        $penawaran = $this->seedPenawaran();

        $pengajuan = PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $this->unit->id,
            'alokasi_anggaran' => 1_000_000,
            'status' => EnumStatusPengajuan::Draft->value,
        ]);

        Livewire::test(ViewPengajuanProgramKerja::class, ['record' => $pengajuan->getRouteKey()])
            ->assertOk()
            ->assertSee('Tahapan Pengajuan');
    }
}
