<?php

namespace Tests\Feature;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Resources\VerifikasiPengajuans\Pages\ListVerifikasiPengajuans;
use App\Filament\Resources\VerifikasiPengajuans\VerifikasiPengajuanResource;
use App\Filament\Resources\VerifikasiRektors\Pages\ListVerifikasiRektors;
use App\Filament\Resources\VerifikasiRektors\VerifikasiRektorResource;
use App\Filament\Resources\VerifikasiWakilRektors\VerifikasiWakilRektorResource;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VerificationStageTabsTest extends TestCase
{
    use RefreshDatabase;

    private UnitKerja $unit;

    private PenawaranProgramKerja $penawaran;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $periode = Periode::create(['name' => 'P1', 'start_datetime' => now(), 'end_datetime' => now()->addYear()]);
        $tk = TahunKerja::create(['periode_id' => $periode->id, 'name' => 'TA 2026', 'start_datetime' => now(), 'end_datetime' => now()->addYear(), 'status' => EnumStatusTahunKerja::Berjalan]);
        $this->unit = UnitKerja::create(['name' => 'Unit A']);
        $this->penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $tk->id, 'unit_kerja_id' => $this->unit->id,
            'bidang_id' => Bidang::create(['code' => 'B1', 'name' => 'B'])->id,
            'kategori_id' => Kategori::create(['code' => 'K1', 'name' => 'K'])->id,
            'program_id' => Program::create(['name' => 'P'])->id,
            'name' => 'Prokerja', 'is_active' => true,
        ]);
    }

    private function pengajuan(EnumStatusPengajuan $status, bool $verified = false): PengajuanProgramKerja
    {
        return PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $this->penawaran->id,
            'unit_kerja_id' => $this->unit->id,
            'alokasi_anggaran' => 10000000,
            'status' => $status,
            'verifikator_id' => $verified ? auth()->id() : null,
            'diverifikasi_at' => $verified ? now() : null,
        ]);
    }

    private function realisasi(array $attributes): RealisasiProgramKerja
    {
        $pengajuan = $this->pengajuan(EnumStatusPengajuan::Diterima, true);

        return RealisasiProgramKerja::create(array_merge([
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'name' => 'Kegiatan',
            'anggaran_digunakan' => 8000000,
        ], $attributes));
    }

    public function test_pengajuan_tabs_partition_records(): void
    {
        $pending = $this->pengajuan(EnumStatusPengajuan::Diajukan);
        $accepted = $this->pengajuan(EnumStatusPengajuan::Diterima, verified: true);
        $revised = $this->pengajuan(EnumStatusPengajuan::Revisi, verified: true);
        $rejected = $this->pengajuan(EnumStatusPengajuan::Ditolak, verified: true);

        $this->assertSame([$pending->id], VerifikasiPengajuanResource::pendingStageQuery()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$accepted->id, $revised->id], VerifikasiPengajuanResource::respondedStageQuery()->pluck('id')->all());
        $this->assertSame([$rejected->id], VerifikasiPengajuanResource::rejectedStageQuery()->pluck('id')->all());
    }

    public function test_rektor_rejection_attributed_to_rektor_not_wakil(): void
    {
        // Ditolak oleh Rektor: rektor_id terisi, wakil_id kosong.
        $rejectedByRektor = $this->realisasi([
            'status' => EnumStatusRealisasi::Ditolak,
            'rektor_id' => auth()->id(),
            'disetujui_rektor_at' => now(),
        ]);

        // Disetujui Rektor lalu ditolak Wakil: kedua aktor terisi.
        $rejectedByWakil = $this->realisasi([
            'status' => EnumStatusRealisasi::Ditolak,
            'rektor_id' => auth()->id(),
            'disetujui_rektor_at' => now(),
            'wakil_id' => auth()->id(),
            'disetujui_wakil_at' => now(),
        ]);

        // Menunggu keputusan Rektor.
        $pendingRektor = $this->realisasi(['status' => EnumStatusRealisasi::Diajukan]);

        // Rektor: ditolak hanya yang benar-benar ditolak di tahap Rektor.
        $this->assertSame([$rejectedByRektor->id], VerifikasiRektorResource::rejectedStageQuery()->pluck('id')->all());
        // Rektor: yang disetujui-lalu-ditolak-Wakil tetap dihitung "sudah direspon" oleh Rektor.
        $this->assertSame([$rejectedByWakil->id], VerifikasiRektorResource::respondedStageQuery()->pluck('id')->all());
        $this->assertSame([$pendingRektor->id], VerifikasiRektorResource::pendingStageQuery()->pluck('id')->all());

        // Wakil: penolakan diatribusikan ke Wakil.
        $this->assertSame([$rejectedByWakil->id], VerifikasiWakilRektorResource::rejectedStageQuery()->pluck('id')->all());
    }

    public function test_list_pages_render_each_tab(): void
    {
        $this->pengajuan(EnumStatusPengajuan::Diajukan);
        $this->pengajuan(EnumStatusPengajuan::Ditolak, verified: true);

        foreach (['perlu', 'direspon', 'ditolak'] as $tab) {
            Livewire::test(ListVerifikasiPengajuans::class)
                ->set('activeTab', $tab)
                ->assertOk();
        }

        Livewire::test(ListVerifikasiRektors::class)->set('activeTab', 'direspon')->assertOk();
    }
}
