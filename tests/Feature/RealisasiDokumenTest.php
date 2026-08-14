<?php

namespace Tests\Feature;

use App\Enums\EnumJenisDokumenRealisasi;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Resources\DaftarProgramKerjas\Pages\ViewDaftarProgramKerja;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\RealisasiProgramKerja;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RealisasiDokumenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_menyimpan_proposal_path_membuat_dokumen(): void
    {
        $realisasi = $this->seedRealisasi();

        $realisasi->update(['proposal_path' => 'proposal-realisasi/a.pdf']);

        $this->assertCount(1, $realisasi->proposals()->get());
        $this->assertSame('a.pdf', $realisasi->proposals()->first()->name);
        $this->assertSame(EnumJenisDokumenRealisasi::Proposal, $realisasi->proposals()->first()->type);
    }

    public function test_menyimpan_nama_asli_dan_ukuran_berkas(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('proposal-realisasi/a1b2c3.pdf', str_repeat('x', 2048));

        $realisasi = $this->seedRealisasi();

        $realisasi->update([
            'proposal_path' => ['proposal-realisasi/a1b2c3.pdf'],
            'proposal_original_names' => ['proposal-realisasi/a1b2c3.pdf' => 'Proposal Kegiatan.pdf'],
        ]);

        $dokumen = $realisasi->proposals()->first();

        $this->assertSame('Proposal Kegiatan.pdf', $dokumen->original_name);
        $this->assertSame('Proposal Kegiatan.pdf', $dokumen->nama_tampilan);
        $this->assertSame(2048, $dokumen->size);
        $this->assertSame('2.0 KB', $dokumen->ukuran_terbaca);
    }

    public function test_nama_tampilan_jatuh_ke_nama_penyimpanan_tanpa_nama_asli(): void
    {
        $realisasi = $this->seedRealisasi();

        $realisasi->update(['proposal_path' => 'proposal-realisasi/a.pdf']);

        $dokumen = $realisasi->proposals()->first();

        $this->assertSame('a.pdf', $dokumen->original_name);
        $this->assertSame('a.pdf', $dokumen->nama_tampilan);
        $this->assertNull($dokumen->size);
        $this->assertNull($dokumen->ukuran_terbaca);
    }

    public function test_path_yang_sama_tidak_menduplikasi_dokumen(): void
    {
        $realisasi = $this->seedRealisasi();

        $realisasi->update(['proposal_path' => 'proposal-realisasi/a.pdf']);
        $realisasi->update(['proposal_path' => 'proposal-realisasi/a.pdf']);

        $this->assertCount(1, $realisasi->proposals()->get());
    }

    public function test_beberapa_proposal_dan_laporan_terkumpul(): void
    {
        $realisasi = $this->seedRealisasi();

        $realisasi->update(['proposal_path' => 'proposal-realisasi/a.pdf']);
        $realisasi->update(['proposal_path' => 'proposal-realisasi/b.pdf']);
        $realisasi->update(['laporan_path' => 'laporan-realisasi/x.pdf']);
        $realisasi->update(['laporan_path' => 'laporan-realisasi/y.pdf']);

        $this->assertCount(2, $realisasi->proposals()->get());
        $this->assertCount(2, $realisasi->laporans()->get());
    }

    public function test_halaman_detail_menampilkan_aksi_dokumen(): void
    {
        $realisasi = $this->seedRealisasi();
        $realisasi->update([
            'proposal_path' => 'proposal-realisasi/a.pdf',
            'laporan_path' => 'laporan-realisasi/x.pdf',
        ]);

        $penawaran = $realisasi->pengajuanProgramKerja->penawaranProgramKerja;

        Livewire::test(ViewDaftarProgramKerja::class, ['record' => $penawaran->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Pelaksanaan Workshop')
            ->assertSee('Detail Kegiatan')
            ->assertSee('Proposal')
            ->assertSee('Laporan');
    }

    /**
     * Daftar berkas dirender component `realisasi-program-kerja-documents`:
     * "nama (ukuran)" pada baris utama, tanggal unggah pada baris kecil di bawahnya.
     */
    public function test_component_dokumen_menampilkan_nama_ukuran_dan_tanggal(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('proposal-realisasi/a1b2c3.pdf', str_repeat('x', 2048));

        $realisasi = $this->seedRealisasi();
        $realisasi->update([
            'proposal_path' => ['proposal-realisasi/a1b2c3.pdf'],
            'proposal_original_names' => ['proposal-realisasi/a1b2c3.pdf' => 'Proposal Kegiatan.pdf'],
        ]);
        $realisasi->proposals()->first()->update(['uploaded_at' => now()->setDate(2026, 7, 20)->setTime(9, 30)]);

        Livewire::test('realisasi-program-kerja-documents', [
            'record' => $realisasi,
            'relationship' => 'proposals',
            'emptyLabel' => 'Belum ada proposal.',
        ])
            ->assertOk()
            ->assertSee('Proposal Kegiatan.pdf')
            ->assertSee('2.0 KB')
            ->assertSee('Diunggah 20 Juli 2026, 09:30');
    }

    /**
     * Pada Daftar Program Kerja tiap berkas tetap punya tombol pratinjau sendiri,
     * dan aksinya menunjuk ke berkas baris yang diklik.
     */
    public function test_component_dokumen_pratinjau_per_berkas(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('proposal-realisasi/a.pdf', 'x');
        Storage::disk('local')->put('proposal-realisasi/b.pdf', 'y');

        $realisasi = $this->seedRealisasi();
        $realisasi->update(['proposal_path' => 'proposal-realisasi/a.pdf']);
        $realisasi->update(['proposal_path' => 'proposal-realisasi/b.pdf']);

        $kedua = $realisasi->proposals()->where('name', 'b.pdf')->first();

        Livewire::test('realisasi-program-kerja-documents', [
            'record' => $realisasi,
            'relationship' => 'proposals',
            'emptyLabel' => 'Belum ada proposal.',
            'dapatPratinjau' => true,
        ])
            ->assertOk()
            ->assertSee('a.pdf')
            ->assertSee('b.pdf')
            // Terlihat hanya bila argumen berhasil dipetakan ke path berkas
            // (MediaAction menyembunyikan diri saat tidak ada path).
            ->assertActionVisible(TestAction::make('pratinjau')->arguments(['dokumen' => $kedua->getKey()]))
            ->assertActionHidden(TestAction::make('pratinjau')->arguments(['dokumen' => 'tidak-ada']));
    }

    protected function seedRealisasi(float $alokasi = 20_000_000): RealisasiProgramKerja
    {
        $periode = Periode::create(['name' => 'P1', 'start_datetime' => now(), 'end_datetime' => now()->addYear()]);
        $tahunKerja = TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA 2026',
            'start_datetime' => now(),
            'end_datetime' => now()->addYear(),
            'status' => EnumStatusTahunKerja::Berjalan,
        ]);
        $unit = UnitKerja::create(['name' => 'Fakultas Teknik']);
        auth()->user()->update(['unit_kerja_id' => $unit->id]);

        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $tahunKerja->id,
            'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::create(['code' => 'B1', 'name' => 'Akademik'])->id,
            'kategori_id' => Kategori::create(['code' => 'K1', 'name' => 'Pendidikan'])->id,
            'program_id' => Program::create(['name' => 'Tridharma'])->id,
            'name' => 'Workshop Kurikulum',
            'is_active' => true,
        ]);

        $pengajuan = PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $unit->id,
            'alokasi_anggaran' => $alokasi,
            'status' => EnumStatusPengajuan::Diterima,
        ]);

        return RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'name' => 'Pelaksanaan Workshop',
            'anggaran_digunakan' => $alokasi,
            'status' => EnumStatusRealisasi::MenungguLaporan,
        ]);
    }
}
