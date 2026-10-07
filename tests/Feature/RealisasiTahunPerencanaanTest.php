<?php

namespace Tests\Feature;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
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
use App\Models\Setting;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\TransisiTahunKerja;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tahun kerja yang masih direncanakan tidak boleh disentuh realisasi sama sekali,
 * dan unit kerja yang menyisakan realisasi belum tuntas di tahun sebelumnya belum
 * boleh mengajukan realisasi tahun baru.
 */
class RealisasiTahunPerencanaanTest extends TestCase
{
    use RefreshDatabase;

    protected TahunKerja $berjalan;

    protected TahunKerja $perencanaan;

    protected UnitKerja $unit;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filament.default_filesystem_disk'));

        $periode = Periode::create([
            'name' => 'Periode 2024-2028',
            'start_datetime' => now()->subYears(2),
            'end_datetime' => now()->addYears(2),
        ]);

        $this->berjalan = TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA 2026',
            'tahun' => 2026,
            'start_datetime' => now(),
            'end_datetime' => now()->addYear(),
            'status' => EnumStatusTahunKerja::Berjalan,
        ]);

        $this->perencanaan = TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA 2027',
            'tahun' => 2027,
            'start_datetime' => now()->addYear(),
            'end_datetime' => now()->addYears(2),
            'status' => EnumStatusTahunKerja::Perencanaan,
        ]);

        $this->unit = UnitKerja::create(['name' => 'Fakultas Teknik']);

        $this->actingAs(User::factory()->create(['unit_kerja_id' => $this->unit->id]));
    }

    public function test_pengajuan_tahun_perencanaan_yang_sudah_diterima_tidak_muncul_sebagai_pilihan_realisasi(): void
    {
        $pengajuanBerjalan = $this->pengajuan($this->berjalan, EnumStatusPengajuan::Diterima);
        $pengajuanPerencanaan = $this->pengajuan($this->perencanaan, EnumStatusPengajuan::Diterima);

        // Pengajuan di luar daftar pilihan ditolak validasi relasi Select.
        Livewire::test(CreateRealisasiProgramKerja::class)
            ->assertOk()
            ->fillForm($this->dataRealisasi($pengajuanPerencanaan))
            ->call('create')
            ->assertHasFormErrors(['pengajuan_program_kerja_id']);

        $this->assertDatabaseCount(RealisasiProgramKerja::class, 0);

        Livewire::test(CreateRealisasiProgramKerja::class)
            ->fillForm($this->dataRealisasi($pengajuanBerjalan))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(RealisasiProgramKerja::class, [
            'pengajuan_program_kerja_id' => $pengajuanBerjalan->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function dataRealisasi(PengajuanProgramKerja $pengajuan): array
    {
        return [
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'name' => 'Pelatihan Dosen',
            'anggaran_digunakan' => 1_000_000,
            ...$this->dataProposal(),
        ];
    }

    public function test_tombol_tambah_realisasi_tertutup_saat_tidak_ada_tahun_berjalan(): void
    {
        $this->assertTrue(RealisasiProgramKerjaResource::canCreate());

        $this->berjalan->update(['status' => EnumStatusTahunKerja::Penutupan]);

        $this->assertFalse(RealisasiProgramKerjaResource::canCreate());
    }

    public function test_realisasi_tahun_perencanaan_tidak_dapat_diajukan(): void
    {
        $realisasi = $this->realisasi($this->perencanaan, EnumStatusRealisasi::Draft);

        $this->assertFalse($realisasi->dapatDiajukan());
    }

    public function test_realisasi_tahun_berjalan_dapat_diajukan(): void
    {
        $realisasi = $this->realisasi($this->berjalan, EnumStatusRealisasi::Draft);

        $this->assertTrue($realisasi->dapatDiajukan());
    }

    public function test_tunggakan_tahun_penutupan_menahan_realisasi_tahun_baru(): void
    {
        // TA 2026 berjalan dengan satu realisasi yang belum tuntas, lalu TA 2027 mulai.
        $tunggakan = $this->realisasi($this->berjalan, EnumStatusRealisasi::MenungguLaporan);

        app(TransisiTahunKerja::class)->mulaiTahunKerja($this->perencanaan);

        $this->assertSame(EnumStatusTahunKerja::Penutupan, $this->berjalan->refresh()->status);

        $draftTahunBaru = $this->realisasi($this->perencanaan->refresh(), EnumStatusRealisasi::Draft);

        $this->assertTrue(
            RealisasiProgramKerja::tunggakanTahunLampau($this->unit->id)->whereKey($tunggakan->id)->exists(),
        );
        $this->assertFalse($draftTahunBaru->dapatDiajukan());

        Livewire::test(ListRealisasiProgramKerjas::class)
            ->callAction(TestAction::make('ajukanRealisasi')->table($draftTahunBaru), $this->dataProposal())
            ->assertNotified('Masih ada realisasi tahun sebelumnya yang belum tuntas');

        $this->assertSame(EnumStatusRealisasi::Draft, $draftTahunBaru->refresh()->status);
    }

    public function test_realisasi_tahun_baru_terbuka_setelah_tunggakan_tuntas(): void
    {
        $tunggakan = $this->realisasi($this->berjalan, EnumStatusRealisasi::MenungguLaporan);

        app(TransisiTahunKerja::class)->mulaiTahunKerja($this->perencanaan);

        $draftTahunBaru = $this->realisasi($this->perencanaan->refresh(), EnumStatusRealisasi::Draft);

        $tunggakan->update(['status' => EnumStatusRealisasi::Selesai]);

        $this->assertTrue($draftTahunBaru->refresh()->dapatDiajukan());

        Livewire::test(ListRealisasiProgramKerjas::class)
            ->callAction(TestAction::make('ajukanRealisasi')->table($draftTahunBaru), $this->dataProposal())
            ->assertNotified('Realisasi berhasil diajukan');

        $this->assertSame(EnumStatusRealisasi::Diajukan, $draftTahunBaru->refresh()->status);
    }

    /**
     * Bila aturan tunggakan dimatikan di Pengaturan Sistem, unit kerja tetap boleh
     * mengajukan realisasi tahun baru; tunggakannya hanya menjadi peringatan.
     */
    public function test_tunggakan_tidak_menahan_realisasi_baru_saat_aturan_dimatikan(): void
    {
        Setting::set(Setting::BLOKIR_TUNGGAKAN_TAHUN_LALU, 0);

        $this->realisasi($this->berjalan, EnumStatusRealisasi::MenungguLaporan);

        app(TransisiTahunKerja::class)->mulaiTahunKerja($this->perencanaan);

        $draftTahunBaru = $this->realisasi($this->perencanaan->refresh(), EnumStatusRealisasi::Draft);

        $this->assertTrue($draftTahunBaru->dapatDiajukan());

        Livewire::test(ListRealisasiProgramKerjas::class)
            ->mountAction(TestAction::make('ajukanRealisasi')->table($draftTahunBaru))
            ->assertMountedActionModalSee('segera selesaikan lewat menu Penyelesaian Tahun Lalu');

        Livewire::test(ListRealisasiProgramKerjas::class)
            ->callAction(TestAction::make('ajukanRealisasi')->table($draftTahunBaru), $this->dataProposal())
            ->assertNotified('Realisasi berhasil diajukan');

        $this->assertSame(EnumStatusRealisasi::Diajukan, $draftTahunBaru->refresh()->status);
    }

    /**
     * Aturan tunggakan tidak melonggarkan kuota: tunggakan tetap dihitung sebagai
     * realisasi berjalan.
     */
    public function test_tunggakan_tetap_memakai_kuota_saat_aturan_dimatikan(): void
    {
        Setting::set(Setting::BLOKIR_TUNGGAKAN_TAHUN_LALU, 0);
        Setting::set(Setting::MAKS_REALISASI_BERJALAN, 1);

        $this->realisasi($this->berjalan, EnumStatusRealisasi::MenungguLaporan);

        app(TransisiTahunKerja::class)->mulaiTahunKerja($this->perencanaan);

        $draftTahunBaru = $this->realisasi($this->perencanaan->refresh(), EnumStatusRealisasi::Draft);

        $this->assertFalse($draftTahunBaru->dapatDiajukan());

        Livewire::test(ListRealisasiProgramKerjas::class)
            ->callAction(TestAction::make('ajukanRealisasi')->table($draftTahunBaru), $this->dataProposal())
            ->assertNotified('Kuota realisasi berjalan sudah penuh');
    }

    /**
     * Hanya tahun Penutupan (tepat sebelum tahun berjalan) yang dihitung sebagai
     * tunggakan. Sisa realisasi tahun yang sudah Selesai tidak bisa dituntaskan lagi,
     * sehingga tidak menahan pengajuan maupun memakai kuota.
     */
    public function test_sisa_realisasi_tahun_selesai_tidak_menahan_dan_tidak_memakai_kuota(): void
    {
        Setting::set(Setting::MAKS_REALISASI_BERJALAN, 1);

        $sisaLama = $this->realisasi($this->berjalan, EnumStatusRealisasi::MenungguLaporan);

        app(TransisiTahunKerja::class)->mulaiTahunKerja($this->perencanaan);
        $this->berjalan->refresh()->update(['status' => EnumStatusTahunKerja::Selesai]);

        $draftTahunBaru = $this->realisasi($this->perencanaan->refresh(), EnumStatusRealisasi::Draft);

        $this->assertFalse(RealisasiProgramKerja::tunggakanTahunLampau($this->unit->id)->whereKey($sisaLama->id)->exists());
        $this->assertSame(0, RealisasiProgramKerja::berjalanUntukUnit($this->unit->id)->count());
        $this->assertTrue($draftTahunBaru->dapatDiajukan());
    }

    /**
     * @return array<string, mixed>
     */
    protected function dataProposal(): array
    {
        return ['proposal_path' => [UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf')]];
    }

    protected function pengajuan(TahunKerja $tahunKerja, EnumStatusPengajuan $status): PengajuanProgramKerja
    {
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $tahunKerja->id,
            'unit_kerja_id' => $this->unit->id,
            'bidang_id' => Bidang::firstOrCreate(['code' => 'B1'], ['name' => 'Akademik'])->id,
            'kategori_id' => Kategori::firstOrCreate(['code' => 'K1'], ['name' => 'Pendidikan'])->id,
            'program_id' => Program::firstOrCreate(['name' => 'Tridharma'])->id,
            'name' => 'Kegiatan '.fake()->unique()->word(),
            'is_active' => true,
        ]);

        return PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $this->unit->id,
            'user_id' => auth()->id(),
            'alokasi_anggaran' => 10_000_000,
            'status' => $status,
        ]);
    }

    protected function realisasi(TahunKerja $tahunKerja, EnumStatusRealisasi $status): RealisasiProgramKerja
    {
        return RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $this->pengajuan($tahunKerja, EnumStatusPengajuan::Diterima)->id,
            'name' => 'Realisasi '.fake()->unique()->word(),
            'anggaran_digunakan' => 1_000_000,
            'status' => $status,
        ]);
    }
}
