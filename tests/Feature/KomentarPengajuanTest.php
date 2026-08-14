<?php

namespace Tests\Feature;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusTahunKerja;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KomentarPengajuanTest extends TestCase
{
    use RefreshDatabase;

    private function buatPengajuan(): PengajuanProgramKerja
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

        return PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $unit->id,
            'alokasi_anggaran' => 1000000,
            'status' => EnumStatusPengajuan::Diajukan,
        ]);
    }

    public function test_edit_mengisi_nilai_lama_dan_menyimpan_perubahan(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $pengajuan = $this->buatPengajuan();
        $log = $pengajuan->catatKomentar('<p>lama</p>', $user->id);

        Livewire::test('pengajuan-program-kerja-comments', ['record' => $pengajuan])
            ->mountAction(TestAction::make('editKomentar')->arguments(['log' => $log->id]))
            ->assertSchemaStateSet(['catatan' => '<p>lama</p>'])
            ->setActionData(['catatan' => '<p>baru</p>'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('<p>baru</p>', $log->refresh()->properties['catatan']);
        $this->assertArrayHasKey('diedit_at', $log->properties);
    }

    public function test_hapus_menandai_komentar_sebagai_dihapus(): void
    {
        $user = User::factory()->create();
        $pengajuan = $this->buatPengajuan();
        $log = $pengajuan->catatKomentar('<p>halo</p>', $user->id);

        $this->assertFalse($log->sudahDihapus());

        $log->tandaiKomentarDihapus();

        $this->assertTrue($log->refresh()->sudahDihapus());
        $this->assertSame('<p>halo</p>', $log->catatan(), 'Isi komentar tetap tersimpan sebagai penanda.');
    }

    public function test_ubah_komentar_menyimpan_isi_baru_dan_menandai_diedit(): void
    {
        $user = User::factory()->create();
        $pengajuan = $this->buatPengajuan();
        $log = $pengajuan->catatKomentar('<p>lama</p>', $user->id);

        $log->ubahKomentar('<p>baru</p>');

        $this->assertSame('<p>baru</p>', $log->refresh()->catatan());
        $this->assertArrayHasKey('diedit_at', $log->properties);
    }

    public function test_komentar_hanya_dapat_diedit_dalam_30_menit(): void
    {
        $pemilik = User::factory()->create();
        $pengajuan = $this->buatPengajuan();
        $log = $pengajuan->catatKomentar('<p>halo</p>', $pemilik->id);

        $this->assertTrue($log->komentarDapatDieditOleh($pemilik->id));

        $log->forceFill(['created_at' => now()->subMinutes(31)])->save();

        $this->assertFalse($log->fresh()->komentarDapatDieditOleh($pemilik->id), 'Lewat 30 menit komentar tidak dapat diedit lagi.');
        $this->assertTrue($log->fresh()->komentarDapatDiubahOleh($pemilik->id), 'Namun komentar masih dapat dihapus.');
    }

    public function test_komentar_hanya_dapat_diubah_oleh_pemiliknya(): void
    {
        $pemilik = User::factory()->create();
        $orangLain = User::factory()->create();
        $pengajuan = $this->buatPengajuan();
        $log = $pengajuan->catatKomentar('<p>halo</p>', $pemilik->id);

        $this->assertTrue($log->komentarDapatDiubahOleh($pemilik->id));
        $this->assertFalse($log->komentarDapatDiubahOleh($orangLain->id));

        $log->tandaiKomentarDihapus();

        $this->assertFalse($log->refresh()->komentarDapatDiubahOleh($pemilik->id), 'Komentar yang sudah dihapus tidak dapat diubah lagi.');
    }
}
