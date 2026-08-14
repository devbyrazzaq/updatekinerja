<?php

namespace Tests\Feature;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Resources\DaftarProgramKerjas\Pages\ListDaftarProgramKerjas;
use App\Filament\Resources\PengajuanProgramKerjas\Pages\ListPengajuanProgramKerjas;
use App\Filament\Resources\RealisasiProgramKerjas\Pages\CreateRealisasiProgramKerja;
use App\Filament\Resources\RealisasiProgramKerjas\Pages\ListRealisasiProgramKerjas;
use App\Filament\Resources\RealisasiProgramKerjas\RealisasiProgramKerjaResource;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PelaksanaanSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filament.default_filesystem_disk'));

        $this->actingAs(User::factory()->create());
    }

    protected function seedPenawaran(): PenawaranProgramKerja
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
        $bidang = Bidang::create(['code' => 'B1', 'name' => 'Pendidikan']);
        $kategori = Kategori::create(['code' => 'K1', 'name' => 'Rutin']);
        $program = Program::create(['name' => 'Tridharma']);

        return PenawaranProgramKerja::create([
            'tahun_kerja_id' => $tahunKerja->id,
            'unit_kerja_id' => $unit->id,
            'bidang_id' => $bidang->id,
            'kategori_id' => $kategori->id,
            'program_id' => $program->id,
            'name' => 'Workshop Kurikulum',
            'target' => '1 kegiatan',
            'is_active' => true,
        ]);
    }

    public function test_list_pages_render(): void
    {
        Livewire::test(ListDaftarProgramKerjas::class)->assertOk();
        Livewire::test(ListPengajuanProgramKerjas::class)->assertOk();
        Livewire::test(ListRealisasiProgramKerjas::class)->assertOk();
    }

    public function test_ajukan_creates_pengajuan(): void
    {
        $penawaran = $this->seedPenawaran();

        // Pengaju adalah anggota unit kerja penawaran tersebut.
        auth()->user()->update(['unit_kerja_id' => $penawaran->unit_kerja_id]);

        Livewire::test(ListDaftarProgramKerjas::class)
            ->callAction(TestAction::make('ajukan')->table($penawaran), [
                'alokasi_anggaran' => 15000000,
                'deskripsi_kegiatan' => 'Workshop 2 hari',
            ]);

        $this->assertDatabaseHas(PengajuanProgramKerja::class, [
            'penawaran_program_kerja_id' => $penawaran->id,
            'alokasi_anggaran' => 15000000,
            'status' => EnumStatusPengajuan::Diajukan->value,
        ]);
    }

    public function test_can_create_realisasi_from_accepted_pengajuan(): void
    {
        $penawaran = $this->seedPenawaran();
        $pengajuan = PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $penawaran->unit_kerja_id,
            'alokasi_anggaran' => 20000000,
            'status' => EnumStatusPengajuan::Diterima,
        ]);

        Livewire::test(CreateRealisasiProgramKerja::class)
            ->fillForm([
                'pengajuan_program_kerja_id' => $pengajuan->id,
                'name' => 'Pelaksanaan Workshop',
                'anggaran_digunakan' => 18000000,
                'proposal_path' => [UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $realisasi = RealisasiProgramKerja::firstWhere('name', 'Pelaksanaan Workshop');
        $this->assertNotNull($realisasi);
        $this->assertSame($pengajuan->id, $realisasi->pengajuan_program_kerja_id);
        Storage::disk(config('filament.default_filesystem_disk'))->assertExists($realisasi->proposal_path);
    }

    public function test_realisasi_tidak_bisa_dari_pengajuan_belum_diterima(): void
    {
        $penawaran = $this->seedPenawaran();
        $pengajuanDraf = PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $penawaran->unit_kerja_id,
            'alokasi_anggaran' => 20000000,
            'status' => EnumStatusPengajuan::Diajukan,
        ]);

        Livewire::test(CreateRealisasiProgramKerja::class)
            ->fillForm([
                'pengajuan_program_kerja_id' => $pengajuanDraf->id,
                'name' => 'Dari Pengajuan Belum Diterima',
                'anggaran_digunakan' => 18000000,
                'proposal_path' => [UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf')],
            ])
            ->call('create')
            ->assertHasFormErrors(['pengajuan_program_kerja_id']);

        $this->assertDatabaseMissing(RealisasiProgramKerja::class, [
            'name' => 'Dari Pengajuan Belum Diterima',
        ]);
    }

    public function test_dropdown_realisasi_hanya_menampilkan_pengajuan_diterima(): void
    {
        $penawaranA = $this->seedPenawaran();
        $penawaranB = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $penawaranA->tahun_kerja_id,
            'unit_kerja_id' => $penawaranA->unit_kerja_id,
            'bidang_id' => $penawaranA->bidang_id,
            'kategori_id' => $penawaranA->kategori_id,
            'program_id' => $penawaranA->program_id,
            'name' => 'Program Belum Diterima',
            'is_active' => true,
        ]);

        PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaranA->id,
            'unit_kerja_id' => $penawaranA->unit_kerja_id,
            'alokasi_anggaran' => 20000000,
            'status' => EnumStatusPengajuan::Diterima,
        ]);
        PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaranB->id,
            'unit_kerja_id' => $penawaranB->unit_kerja_id,
            'alokasi_anggaran' => 20000000,
            'status' => EnumStatusPengajuan::Diajukan,
        ]);

        $this->get(RealisasiProgramKerjaResource::getUrl('create'))
            ->assertOk()
            ->assertSee('Workshop Kurikulum')
            ->assertDontSee('Program Belum Diterima');
    }

    public function test_realisasi_requires_proposal(): void
    {
        $penawaran = $this->seedPenawaran();
        $pengajuan = PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $penawaran->unit_kerja_id,
            'alokasi_anggaran' => 20000000,
            'status' => EnumStatusPengajuan::Diterima,
        ]);

        Livewire::test(CreateRealisasiProgramKerja::class)
            ->fillForm([
                'pengajuan_program_kerja_id' => $pengajuan->id,
                'name' => 'Tanpa Proposal',
                'anggaran_digunakan' => 18000000,
                'proposal_path' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['proposal_path' => 'required']);
    }

    public function test_realisasi_rejects_overspending(): void
    {
        $penawaran = $this->seedPenawaran();
        $pengajuan = PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $penawaran->unit_kerja_id,
            'alokasi_anggaran' => 20000000,
            'status' => EnumStatusPengajuan::Diterima,
        ]);

        Livewire::test(CreateRealisasiProgramKerja::class)
            ->fillForm([
                'pengajuan_program_kerja_id' => $pengajuan->id,
                'name' => 'Over Budget',
                'anggaran_digunakan' => 25000000,
            ])
            ->call('create')
            ->assertHasFormErrors(['anggaran_digunakan']);
    }
}
