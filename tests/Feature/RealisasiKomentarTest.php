<?php

namespace Tests\Feature;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
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
use Livewire\Livewire;
use Tests\TestCase;

class RealisasiKomentarTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_edit_komentar_memperbarui_isi_komentar_yang_dipilih(): void
    {
        $realisasi = $this->seedRealisasi();
        $lain = $realisasi->catatKomentar('komentar lain', $this->user->id);
        $target = $realisasi->catatKomentar('komentar awal', $this->user->id);

        Livewire::test('realisasi-program-kerja-comments', ['record' => $realisasi])
            ->callAction(
                TestAction::make('editKomentar')->arguments(['log' => $target->getKey()]),
                ['catatan' => 'komentar diperbarui'],
            )
            ->assertHasNoActionErrors();

        $this->assertStringContainsString('komentar diperbarui', (string) $target->refresh()->catatan());
        $this->assertNotNull($target->properties['diedit_at'] ?? null);
        $this->assertStringContainsString('komentar lain', (string) $lain->refresh()->catatan());
    }

    public function test_edit_form_terisi_komentar_lama(): void
    {
        $realisasi = $this->seedRealisasi();
        $log = $realisasi->catatKomentar('<p>isi lama</p>', $this->user->id);

        Livewire::test('realisasi-program-kerja-comments', ['record' => $realisasi])
            ->mountAction(TestAction::make('editKomentar')->arguments(['log' => $log->getKey()]))
            ->assertActionDataSet(['catatan' => '<p>isi lama</p>']);
    }

    public function test_komentar_lebih_dari_30_menit_tidak_dapat_diedit(): void
    {
        $realisasi = $this->seedRealisasi();
        $log = $realisasi->catatKomentar('komentar lama', $this->user->id);
        $log->forceFill(['created_at' => now()->subMinutes(31)])->save();

        $this->assertFalse($log->fresh()->komentarDapatDieditOleh($this->user->id));

        Livewire::test('realisasi-program-kerja-comments', ['record' => $realisasi])
            ->callAction(
                TestAction::make('editKomentar')->arguments(['log' => $log->getKey()]),
                ['catatan' => 'coba ubah'],
            );

        $this->assertStringContainsString('komentar lama', (string) $log->refresh()->catatan());
    }

    public function test_hapus_komentar_menandai_dihapus(): void
    {
        $realisasi = $this->seedRealisasi();
        $log = $realisasi->catatKomentar('mau dihapus', $this->user->id);

        Livewire::test('realisasi-program-kerja-comments', ['record' => $realisasi])
            ->mountAction(TestAction::make('hapusKomentar')->arguments(['log' => $log->getKey()]))
            ->callMountedAction();

        $this->assertTrue($log->refresh()->sudahDihapus());
    }

    public function test_pengguna_lain_tidak_dapat_mengubah_komentar(): void
    {
        $realisasi = $this->seedRealisasi();
        $log = $realisasi->catatKomentar('komentar pemilik', $this->user->id);

        $lain = User::factory()->create(['unit_kerja_id' => $this->user->unit_kerja_id]);
        $this->actingAs($lain);

        Livewire::test('realisasi-program-kerja-comments', ['record' => $realisasi])
            ->callAction(
                TestAction::make('editKomentar')->arguments(['log' => $log->getKey()]),
                ['catatan' => 'diretas'],
            );

        $this->assertStringContainsString('komentar pemilik', (string) $log->refresh()->catatan());
    }

    private function seedRealisasi(float $alokasi = 20_000_000): RealisasiProgramKerja
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
        $this->user->update(['unit_kerja_id' => $unit->id]);

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
            'status' => EnumStatusRealisasi::Draft,
        ]);
    }
}
