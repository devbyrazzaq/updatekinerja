<?php

namespace Tests\Feature;

use App\Enums\EnumStatusAnggaran;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusPenyelesaianAnggaran;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Pages\PengaturanProgramKerja;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\KelompokAcuan;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\RealisasiProgramKerja;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\TransisiTahunKerja;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class TransisiTahunKerjaTest extends TestCase
{
    use RefreshDatabase;

    protected TransisiTahunKerja $transisi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transisi = app(TransisiTahunKerja::class);
    }

    public function test_slot_berjalan_hanya_boleh_dipegang_satu_tahun_kerja(): void
    {
        $this->tahunKerja('TA 2026', 2026, EnumStatusTahunKerja::Berjalan);

        $this->expectException(RuntimeException::class);

        $this->tahunKerja('TA 2027', 2027, EnumStatusTahunKerja::Berjalan);
    }

    public function test_slot_perencanaan_hanya_boleh_dipegang_satu_tahun_kerja(): void
    {
        $this->tahunKerja('TA 2027', 2027, EnumStatusTahunKerja::Perencanaan);
        $lain = $this->tahunKerja('TA 2028', 2028);

        $this->expectException(RuntimeException::class);

        $this->transisi->tetapkanPerencanaan($lain);
    }

    public function test_tahun_berjalan_dan_perencanaan_hidup_berdampingan(): void
    {
        $berjalan = $this->tahunKerja('TA 2026', 2026, EnumStatusTahunKerja::Berjalan);
        $perencanaan = $this->tahunKerja('TA 2027', 2027, EnumStatusTahunKerja::Perencanaan);

        $this->assertSame($berjalan->id, TahunKerja::berjalan()?->id);
        $this->assertSame($perencanaan->id, TahunKerja::perencanaan()?->id);
    }

    public function test_memulai_tahun_perencanaan_menggeser_tahun_lama_ke_penutupan(): void
    {
        $lama = $this->tahunKerja('TA 2026', 2026, EnumStatusTahunKerja::Berjalan);
        $baru = $this->tahunKerja('TA 2027', 2027, EnumStatusTahunKerja::Perencanaan);

        $this->transisi->mulaiTahunKerja($baru);

        $this->assertSame(EnumStatusTahunKerja::Berjalan, $baru->refresh()->status);
        $this->assertSame(EnumStatusTahunKerja::Penutupan, $lama->refresh()->status);
        $this->assertNull(TahunKerja::perencanaan());
    }

    public function test_tahun_kerja_yang_belum_pernah_dijalankan_tetap_bisa_dimulai(): void
    {
        $baru = $this->tahunKerja('TA 2027', 2027);

        $this->transisi->mulaiTahunKerja($baru);

        $this->assertSame(EnumStatusTahunKerja::Berjalan, $baru->refresh()->status);
    }

    public function test_tahun_berjalan_bisa_diakhiri_tanpa_tahun_pengganti(): void
    {
        $tahunKerja = $this->tahunKerja('TA 2026', 2026, EnumStatusTahunKerja::Berjalan);

        $this->transisi->akhiriTahunKerja($tahunKerja);

        $this->assertSame(EnumStatusTahunKerja::Penutupan, $tahunKerja->refresh()->status);
        $this->assertNull(TahunKerja::berjalan());
        $this->assertSame([$tahunKerja->id], TahunKerja::penutupan()->modelKeys());
    }

    public function test_pengakhiran_mencatat_waktu_dan_pelakunya(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $tahunKerja = $this->tahunKerja('TA 2026', 2026, EnumStatusTahunKerja::Berjalan);

        $this->transisi->akhiriTahunKerja($tahunKerja->refresh());

        $this->assertNotNull($tahunKerja->refresh()->ditutup_pada);
        $this->assertSame($user->id, $tahunKerja->ditutup_oleh_id);
        $this->assertNull($tahunKerja->dikunci_pada);
    }

    public function test_tahun_yang_tidak_berjalan_tidak_bisa_diakhiri(): void
    {
        $tahunKerja = $this->tahunKerja('TA 2027', 2027, EnumStatusTahunKerja::Perencanaan);

        $this->expectException(RuntimeException::class);

        $this->transisi->akhiriTahunKerja($tahunKerja);
    }

    public function test_dampak_pengakhiran_menyebut_pekerjaan_yang_dibekukan(): void
    {
        $tahunKerja = $this->tahunKerja('TA 2026', 2026, EnumStatusTahunKerja::Berjalan);
        $this->realisasi($tahunKerja, EnumStatusRealisasi::MenungguLaporan);

        $dampak = $this->transisi->dampakPengakhiran($tahunKerja);

        $this->assertCount(2, $dampak);
        $this->assertStringContainsString('1 realisasi masih berjalan', $dampak[0]);
        $this->assertStringContainsString('1 pengajuan program kerja', $dampak[1]);

        // Berbeda dengan penghambat penguncian, dampak tidak pernah menghalangi.
        $this->transisi->akhiriTahunKerja($tahunKerja);

        $this->assertSame(EnumStatusTahunKerja::Penutupan, $tahunKerja->refresh()->status);
    }

    public function test_pergeseran_lewat_mulai_tahun_kerja_ikut_menstempel_pengakhiran(): void
    {
        $lama = $this->tahunKerja('TA 2026', 2026, EnumStatusTahunKerja::Berjalan);
        $baru = $this->tahunKerja('TA 2027', 2027, EnumStatusTahunKerja::Perencanaan);

        $this->transisi->mulaiTahunKerja($baru);

        $this->assertSame(EnumStatusTahunKerja::Penutupan, $lama->refresh()->status);
        $this->assertNotNull($lama->ditutup_pada);
    }

    public function test_penutupan_bisa_dibatalkan_selama_slot_berjalan_kosong(): void
    {
        $tahunKerja = $this->tahunKerja('TA 2026', 2026, EnumStatusTahunKerja::Berjalan);
        $this->transisi->akhiriTahunKerja($tahunKerja);

        $this->transisi->batalkanPenutupan($tahunKerja->refresh());

        $this->assertSame(EnumStatusTahunKerja::Berjalan, $tahunKerja->refresh()->status);
        $this->assertNull($tahunKerja->ditutup_pada);
        $this->assertNull($tahunKerja->ditutup_oleh_id);
    }

    public function test_pembatalan_penutupan_ditolak_selama_slot_berjalan_terisi(): void
    {
        $lama = $this->tahunKerja('TA 2026', 2026, EnumStatusTahunKerja::Berjalan);
        $baru = $this->tahunKerja('TA 2027', 2027, EnumStatusTahunKerja::Perencanaan);

        $this->transisi->mulaiTahunKerja($baru);

        $this->expectException(RuntimeException::class);

        $this->transisi->batalkanPenutupan($lama->refresh());
    }

    public function test_tahun_yang_tidak_ditutup_tidak_bisa_dibatalkan_penutupannya(): void
    {
        $tahunKerja = $this->tahunKerja('TA 2026', 2026, EnumStatusTahunKerja::Berjalan);

        $this->expectException(RuntimeException::class);

        $this->transisi->batalkanPenutupan($tahunKerja);
    }

    public function test_penguncian_ditolak_selama_realisasi_masih_berjalan(): void
    {
        $tahunKerja = $this->tahunKerja('TA 2026', 2026, EnumStatusTahunKerja::Penutupan);
        $this->realisasi($tahunKerja, EnumStatusRealisasi::MenungguLaporan);

        $penghambat = $this->transisi->penghambatPenguncian($tahunKerja);

        $this->assertCount(1, $penghambat);
        $this->assertStringContainsString('1 realisasi masih berjalan', $penghambat[0]);

        $this->expectException(RuntimeException::class);

        $this->transisi->kunciTahunKerja($tahunKerja);
    }

    public function test_penguncian_ditolak_selama_selisih_anggaran_menunggu_biro_keuangan(): void
    {
        $tahunKerja = $this->tahunKerja('TA 2026', 2026, EnumStatusTahunKerja::Penutupan);
        $realisasi = $this->realisasi($tahunKerja, EnumStatusRealisasi::Selesai);
        $realisasi->update([
            'status_anggaran' => EnumStatusAnggaran::Sisa,
            'nominal_selisih_anggaran' => 500_000,
            'status_penyelesaian_anggaran' => EnumStatusPenyelesaianAnggaran::Menunggu,
        ]);

        $penghambat = $this->transisi->penghambatPenguncian($tahunKerja);

        $this->assertCount(1, $penghambat);
        $this->assertStringContainsString('menunggu penyelesaian selisih anggaran', $penghambat[0]);
    }

    public function test_tahun_kerja_yang_tuntas_bisa_dikunci(): void
    {
        $tahunKerja = $this->tahunKerja('TA 2026', 2026, EnumStatusTahunKerja::Penutupan);
        $realisasi = $this->realisasi($tahunKerja, EnumStatusRealisasi::Selesai);
        $realisasi->update([
            'status_anggaran' => EnumStatusAnggaran::Sisa,
            'nominal_selisih_anggaran' => 500_000,
            'status_penyelesaian_anggaran' => EnumStatusPenyelesaianAnggaran::Dikembalikan,
        ]);

        $this->assertSame([], $this->transisi->penghambatPenguncian($tahunKerja));

        $this->transisi->kunciTahunKerja($tahunKerja);

        $this->assertSame(EnumStatusTahunKerja::Selesai, $tahunKerja->refresh()->status);
    }

    public function test_penguncian_mencatat_waktu_dan_pelakunya(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $tahunKerja = $this->tahunKerja('TA 2026', 2026, EnumStatusTahunKerja::Penutupan);

        $this->transisi->kunciTahunKerja($tahunKerja);

        $this->assertNotNull($tahunKerja->refresh()->dikunci_pada);
        $this->assertSame($user->id, $tahunKerja->dikunci_oleh_id);
    }

    public function test_halaman_mengakhiri_tahun_berjalan_lewat_tombolnya(): void
    {
        $this->actingAs(User::factory()->create());

        $tahunKerja = $this->tahunKerja('TA 2026', 2026, EnumStatusTahunKerja::Berjalan);

        Livewire::test(PengaturanProgramKerja::class)
            ->callAction(TestAction::make('akhiriTahunKerja'), [
                'captcha_count' => 7,
                'captcha_answer' => 7,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(EnumStatusTahunKerja::Penutupan, $tahunKerja->refresh()->status);
        $this->assertNotNull($tahunKerja->ditutup_pada);
    }

    public function test_halaman_menolak_pengakhiran_bila_konfirmasi_salah(): void
    {
        $this->actingAs(User::factory()->create());

        $tahunKerja = $this->tahunKerja('TA 2026', 2026, EnumStatusTahunKerja::Berjalan);

        Livewire::test(PengaturanProgramKerja::class)
            ->callAction(TestAction::make('akhiriTahunKerja'), [
                'captcha_count' => 7,
                'captcha_answer' => 9,
            ]);

        $this->assertSame(EnumStatusTahunKerja::Berjalan, $tahunKerja->refresh()->status);
        $this->assertNull($tahunKerja->ditutup_pada);
    }

    /**
     * Pergantian tahun kerja adalah keputusan tersendiri, bukan efek samping generate
     * penawaran: selama ada tahun berjalan, dropdown penerapan tidak boleh
     * menggesernya diam-diam ke tahun lain.
     */
    public function test_halaman_menutup_penerapan_yang_menggeser_tahun_berjalan(): void
    {
        $this->actingAs(User::factory()->create());

        $this->tahunKerja('TA 2026', 2026, EnumStatusTahunKerja::Berjalan);
        $lain = $this->tahunKerja('TA 2027', 2027);

        Livewire::test(PengaturanProgramKerja::class)
            ->callAction(TestAction::make('ubahBerjalan'), [
                'kelompok_acuan_id' => KelompokAcuan::create([
                    'name' => 'RENSTRA 2025-2029',
                    'tahun_mulai' => 2025,
                    'tahun_selesai' => 2029,
                ])->id,
                'tahun_kerja_id' => $lain->id,
            ])
            ->assertHasNoActionErrors()
            ->assertActionDisabled(TestAction::make('terapkanBerjalan'));

        $this->assertSame(EnumStatusTahunKerja::Selesai, $lain->refresh()->status);
    }

    public function test_halaman_membatalkan_penutupan_lewat_tombolnya(): void
    {
        $this->actingAs(User::factory()->create());

        $tahunKerja = $this->tahunKerja('TA 2026', 2026, EnumStatusTahunKerja::Berjalan);
        $this->transisi->akhiriTahunKerja($tahunKerja);

        Livewire::test(PengaturanProgramKerja::class)
            ->callAction(TestAction::make('batalkanPenutupan'), ['tahun_kerja_id' => $tahunKerja->id])
            ->assertHasNoActionErrors();

        $this->assertSame(EnumStatusTahunKerja::Berjalan, $tahunKerja->refresh()->status);
    }

    protected function tahunKerja(string $name, int $tahun, ?EnumStatusTahunKerja $status = null): TahunKerja
    {
        $periode = Periode::firstOrCreate(
            ['name' => 'Periode 2024-2028'],
            ['start_datetime' => now()->subYears(2), 'end_datetime' => now()->addYears(2)],
        );

        return TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => $name,
            'tahun' => $tahun,
            'start_datetime' => now(),
            'end_datetime' => now()->addYear(),
            'status' => $status ?? EnumStatusTahunKerja::Selesai,
        ]);
    }

    protected function realisasi(TahunKerja $tahunKerja, EnumStatusRealisasi $status): RealisasiProgramKerja
    {
        $unit = UnitKerja::firstOrCreate(['name' => 'Fakultas Teknik']);

        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $tahunKerja->id,
            'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::firstOrCreate(['code' => 'B1'], ['name' => 'Akademik'])->id,
            'kategori_id' => Kategori::firstOrCreate(['code' => 'K1'], ['name' => 'Pendidikan'])->id,
            'program_id' => Program::firstOrCreate(['name' => 'Tridharma'])->id,
            'name' => 'Kegiatan '.fake()->unique()->word(),
            'is_active' => true,
        ]);

        $pengajuan = PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $unit->id,
            'alokasi_anggaran' => 10_000_000,
            'status' => EnumStatusPengajuan::Diterima,
        ]);

        return RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'name' => 'Realisasi '.fake()->unique()->word(),
            'anggaran_digunakan' => 1_000_000,
            'status' => $status,
        ]);
    }
}
