<?php

namespace Tests\Feature;

use App\Enums\EnumRole;
use App\Enums\EnumUrgensiRealisasi;
use App\Models\PengajuanProgramKerja;
use App\Models\RealisasiProgramKerja;
use App\Models\User;
use App\Services\Notifikasi\NotifikasiVerifikasi;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class NotifikasiVerifikasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function buatRektor(): User
    {
        $user = User::factory()->create();
        $user->assignRole(EnumRole::Rektor->value);

        return $user;
    }

    private function notifikasi(): NotifikasiVerifikasi
    {
        return app(NotifikasiVerifikasi::class);
    }

    public function test_pengajuan_diajukan_menotifikasi_seluruh_rektor_dan_bukan_pengaju(): void
    {
        $rektorA = $this->buatRektor();
        $rektorB = $this->buatRektor();
        $pengaju = User::factory()->create();

        $pengajuan = PengajuanProgramKerja::factory()->create(['user_id' => $pengaju->id]);

        $this->notifikasi()->pengajuanDiajukan($pengajuan, diajukanKembali: false);

        $this->assertSame(1, $rektorA->notifications()->count());
        $this->assertSame(1, $rektorB->notifications()->count());
        $this->assertSame(0, $pengaju->notifications()->count());
        $this->assertStringContainsString(
            'Pengajuan menunggu verifikasi',
            json_encode($rektorA->notifications()->first()->data),
        );
    }

    public function test_pengajuan_diajukan_kembali_memakai_judul_berbeda(): void
    {
        $rektor = $this->buatRektor();
        $pengajuan = PengajuanProgramKerja::factory()->create();

        $this->notifikasi()->pengajuanDiajukan($pengajuan, diajukanKembali: true);

        $this->assertStringContainsString(
            'Pengajuan diajukan kembali',
            json_encode($rektor->notifications()->first()->data),
        );
    }

    public function test_pengajuan_revisi_menotifikasi_pengaju_asli_saja(): void
    {
        $rektor = $this->buatRektor();
        $pengaju = User::factory()->create();
        $pengajuan = PengajuanProgramKerja::factory()->create(['user_id' => $pengaju->id]);

        $this->notifikasi()->pengajuanRevisi($pengajuan, '<p>Perbaiki rincian anggaran.</p>');

        $this->assertSame(1, $pengaju->notifications()->count());
        $this->assertSame(0, $rektor->notifications()->count());

        $data = json_encode($pengaju->notifications()->first()->data);
        $this->assertStringContainsString('perlu direvisi', $data);
        $this->assertStringContainsString('Perbaiki rincian anggaran.', $data);
    }

    public function test_pengajuan_revisi_tanpa_pengaju_tidak_mengirim_notifikasi(): void
    {
        $this->buatRektor();
        $pengajuan = PengajuanProgramKerja::factory()->create(['user_id' => null]);

        $this->notifikasi()->pengajuanRevisi($pengajuan, 'Catatan apa pun');

        $this->assertSame(0, DatabaseNotification::query()->count());
    }

    public function test_realisasi_diajukan_menotifikasi_verifikator_rektor(): void
    {
        $rektor = $this->buatRektor();
        $realisasi = RealisasiProgramKerja::factory()->create();

        $this->notifikasi()->realisasiDiajukan($realisasi, diajukanKembali: false);

        $this->assertSame(1, $rektor->notifications()->count());
        $this->assertStringContainsString(
            'Realisasi menunggu verifikasi',
            json_encode($rektor->notifications()->first()->data),
        );
    }

    public function test_realisasi_diajukan_menyertakan_urgensi_di_notifikasi(): void
    {
        $rektor = $this->buatRektor();
        $realisasi = RealisasiProgramKerja::factory()->create([
            'urgensi' => EnumUrgensiRealisasi::Tinggi,
        ]);

        $this->notifikasi()->realisasiDiajukan($realisasi, diajukanKembali: false);

        $this->assertStringContainsString(
            'Urgensi: Mendesak',
            json_encode($rektor->notifications()->first()->data),
        );
    }

    public function test_realisasi_revisi_menotifikasi_pengaju_induknya(): void
    {
        $rektor = $this->buatRektor();
        $pengaju = User::factory()->create();
        $pengajuan = PengajuanProgramKerja::factory()->create(['user_id' => $pengaju->id]);
        $realisasi = RealisasiProgramKerja::factory()->create([
            'pengajuan_program_kerja_id' => $pengajuan->id,
        ]);

        $this->notifikasi()->realisasiRevisi($realisasi, 'Lampirkan bukti tambahan.');

        $this->assertSame(1, $pengaju->notifications()->count());
        $this->assertSame(0, $rektor->notifications()->count());
        $this->assertStringContainsString(
            'Lampirkan bukti tambahan.',
            json_encode($pengaju->notifications()->first()->data),
        );
    }
}
