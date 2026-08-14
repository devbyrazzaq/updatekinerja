<?php

namespace Tests\Feature;

use App\Enums\EnumModeGenerate;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Pages\PengaturanProgramKerja;
use App\Models\AcuanProgramKerja;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\KelompokAcuan;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\GeneratePenawaranFromAcuan;
use App\Services\KonteksProgramKerja;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class GeneratePenawaranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /**
     * @return array{0: AcuanProgramKerja, 1: TahunKerja, 2: KelompokAcuan}
     */
    private function seedAcuan(bool $withTarget = true): array
    {
        $periode = Periode::create(['name' => 'P1', 'start_datetime' => now(), 'end_datetime' => now()->addYear()]);
        $tahunKerja = TahunKerja::create(['periode_id' => $periode->id, 'name' => 'TA 2026', 'tahun' => now()->year, 'start_datetime' => now(), 'end_datetime' => now()->addYear(), 'status' => EnumStatusTahunKerja::Berjalan]);
        $kelompokAcuan = KelompokAcuan::create(['name' => 'RENSTRA 2025-2029', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'is_active' => true]);

        $acuan = AcuanProgramKerja::create([
            'name' => 'Peningkatan Mutu',
            'kelompok_acuan_id' => $kelompokAcuan->id,
            'unit_kerja_id' => UnitKerja::create(['name' => 'Fakultas Teknik'])->id,
            'bidang_id' => Bidang::create(['code' => 'B1', 'name' => 'Akademik'])->id,
            'kategori_id' => Kategori::create(['code' => 'K1', 'name' => 'Pendidikan'])->id,
            'program_id' => Program::create(['name' => 'Tridharma'])->id,
            'nilai_standar' => '90',
            'satuan_nilai_standar' => 'persen',
            'is_active' => true,
        ]);

        if ($withTarget) {
            $acuan->targets()->create(['tahun' => $tahunKerja->tahunTarget(), 'nilai' => '95', 'satuan' => 'persen']);
        }

        return [$acuan, $tahunKerja, $kelompokAcuan];
    }

    private function seedPengajuan(PenawaranProgramKerja $penawaran): PengajuanProgramKerja
    {
        return PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $penawaran->unit_kerja_id,
            'user_id' => auth()->id(),
            'alokasi_anggaran' => 1_000_000,
            'status' => EnumStatusPengajuan::Diajukan,
        ]);
    }

    public function test_service_generates_penawaran_with_target(): void
    {
        [$acuan, $tahunKerja, $kelompokAcuan] = $this->seedAcuan();

        $result = (new GeneratePenawaranFromAcuan)->handle($kelompokAcuan, $tahunKerja, EnumModeGenerate::Baru);

        $this->assertSame(1, $result['created']);
        $this->assertDatabaseHas(PenawaranProgramKerja::class, [
            'acuan_program_kerja_id' => $acuan->id,
            'tahun_kerja_id' => $tahunKerja->id,
            'name' => 'Peningkatan Mutu',
            'target' => '95 persen',
        ]);
    }

    public function test_service_only_generates_acuan_of_the_chosen_kelompok(): void
    {
        [, $tahunKerja, $kelompokAcuan] = $this->seedAcuan();

        $kelompokLain = KelompokAcuan::create(['name' => 'RENSTRA 2020-2024', 'tahun_mulai' => 2020, 'tahun_selesai' => 2024]);
        AcuanProgramKerja::create([
            'name' => 'Acuan Kelompok Lain',
            'kelompok_acuan_id' => $kelompokLain->id,
            'unit_kerja_id' => UnitKerja::create(['name' => 'Fakultas Hukum'])->id,
            'bidang_id' => Bidang::query()->firstOrFail()->id,
            'kategori_id' => Kategori::query()->firstOrFail()->id,
            'program_id' => Program::query()->firstOrFail()->id,
            'is_active' => true,
        ]);

        (new GeneratePenawaranFromAcuan)->handle($kelompokAcuan, $tahunKerja, EnumModeGenerate::Baru);

        $this->assertDatabaseCount(PenawaranProgramKerja::class, 1);
        $this->assertDatabaseMissing(PenawaranProgramKerja::class, ['name' => 'Acuan Kelompok Lain']);
    }

    public function test_mode_baru_is_rejected_when_penawaran_already_exists(): void
    {
        [, $tahunKerja, $kelompokAcuan] = $this->seedAcuan();

        (new GeneratePenawaranFromAcuan)->handle($kelompokAcuan, $tahunKerja, EnumModeGenerate::Baru);

        $this->expectException(RuntimeException::class);

        (new GeneratePenawaranFromAcuan)->handle($kelompokAcuan, $tahunKerja, EnumModeGenerate::Baru);
    }

    public function test_mode_sinkron_updates_existing_penawaran(): void
    {
        [$acuan, $tahunKerja, $kelompokAcuan] = $this->seedAcuan();

        (new GeneratePenawaranFromAcuan)->handle($kelompokAcuan, $tahunKerja, EnumModeGenerate::Baru);

        $acuan->update(['name' => 'Peningkatan Mutu Revisi']);

        $result = (new GeneratePenawaranFromAcuan)->handle($kelompokAcuan, $tahunKerja, EnumModeGenerate::Sinkron);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertDatabaseCount(PenawaranProgramKerja::class, 1);
        $this->assertDatabaseHas(PenawaranProgramKerja::class, ['name' => 'Peningkatan Mutu Revisi']);
    }

    public function test_mode_sinkron_is_allowed_even_when_penawaran_has_pengajuan(): void
    {
        [, $tahunKerja, $kelompokAcuan] = $this->seedAcuan();

        (new GeneratePenawaranFromAcuan)->handle($kelompokAcuan, $tahunKerja, EnumModeGenerate::Baru);
        $penawaran = PenawaranProgramKerja::query()->firstOrFail();
        $pengajuan = $this->seedPengajuan($penawaran);

        (new GeneratePenawaranFromAcuan)->handle($kelompokAcuan, $tahunKerja, EnumModeGenerate::Sinkron);

        $this->assertDatabaseHas(PengajuanProgramKerja::class, ['id' => $pengajuan->id]);
        $this->assertDatabaseHas(PenawaranProgramKerja::class, ['id' => $penawaran->id]);
    }

    public function test_mode_ulang_rebuilds_penawaran_from_scratch(): void
    {
        [, $tahunKerja, $kelompokAcuan] = $this->seedAcuan();

        (new GeneratePenawaranFromAcuan)->handle($kelompokAcuan, $tahunKerja, EnumModeGenerate::Baru);
        $penawaranLama = PenawaranProgramKerja::query()->firstOrFail();

        $result = (new GeneratePenawaranFromAcuan)->handle($kelompokAcuan, $tahunKerja, EnumModeGenerate::Ulang);

        $this->assertSame(1, $result['deleted']);
        $this->assertSame(1, $result['created']);
        $this->assertDatabaseCount(PenawaranProgramKerja::class, 1);
        $this->assertDatabaseMissing(PenawaranProgramKerja::class, ['id' => $penawaranLama->id]);
    }

    public function test_mode_ulang_fails_when_penawaran_already_has_pengajuan(): void
    {
        [, $tahunKerja, $kelompokAcuan] = $this->seedAcuan();

        (new GeneratePenawaranFromAcuan)->handle($kelompokAcuan, $tahunKerja, EnumModeGenerate::Baru);
        $penawaran = PenawaranProgramKerja::query()->firstOrFail();
        $pengajuan = $this->seedPengajuan($penawaran);

        try {
            (new GeneratePenawaranFromAcuan)->handle($kelompokAcuan, $tahunKerja, EnumModeGenerate::Ulang);
            $this->fail('Generate ulang seharusnya ditolak karena penawaran sudah memiliki pengajuan.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('sudah memiliki pengajuan', $exception->getMessage());
        }

        $this->assertDatabaseHas(PenawaranProgramKerja::class, ['id' => $penawaran->id]);
        $this->assertDatabaseHas(PengajuanProgramKerja::class, ['id' => $pengajuan->id]);
    }

    public function test_mode_lewati_leaves_penawaran_untouched(): void
    {
        [$acuan, $tahunKerja, $kelompokAcuan] = $this->seedAcuan();

        (new GeneratePenawaranFromAcuan)->handle($kelompokAcuan, $tahunKerja, EnumModeGenerate::Baru);
        $acuan->update(['name' => 'Nama Baru yang Tidak Boleh Menular']);

        $result = (new GeneratePenawaranFromAcuan)->handle($kelompokAcuan, $tahunKerja, EnumModeGenerate::Lewati);

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['deleted']);
        $this->assertDatabaseHas(PenawaranProgramKerja::class, ['name' => 'Peningkatan Mutu']);
    }

    /**
     * Target penawaran mengikuti kolom Tahun pada tahun kerja, bukan tahun tanggal mulainya.
     */
    public function test_target_follows_the_tahun_column(): void
    {
        [$acuan, $tahunKerja, $kelompokAcuan] = $this->seedAcuan();

        $tahunLain = now()->year + 1;
        $tahunKerja->update(['tahun' => $tahunLain]);
        $acuan->targets()->create(['tahun' => $tahunLain, 'nilai' => '99', 'satuan' => 'persen']);

        (new GeneratePenawaranFromAcuan)->handle($kelompokAcuan, $tahunKerja, EnumModeGenerate::Baru);

        $this->assertDatabaseHas(PenawaranProgramKerja::class, ['target' => '99 persen']);
        $this->assertDatabaseMissing(PenawaranProgramKerja::class, ['target' => '95 persen']);
    }

    public function test_service_skips_acuan_without_target_when_flag_set(): void
    {
        [, $tahunKerja, $kelompokAcuan] = $this->seedAcuan(withTarget: false);

        $result = (new GeneratePenawaranFromAcuan)->handle($kelompokAcuan, $tahunKerja, EnumModeGenerate::Baru, onlyWithTarget: true);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseCount(PenawaranProgramKerja::class, 0);
    }

    public function test_page_applies_context_and_generates_penawaran(): void
    {
        [, $tahunKerja, $kelompokAcuan] = $this->seedAcuan();

        $kelompokAcuan->update(['is_active' => false]);
        $tahunKerja->update(['status' => EnumStatusTahunKerja::Selesai]);

        Livewire::test(PengaturanProgramKerja::class)
            ->fillForm([
                'berjalan_kelompok_acuan_id' => $kelompokAcuan->id,
                'berjalan_tahun_kerja_id' => $tahunKerja->id,
            ])
            ->callAction(TestAction::make('terapkanBerjalan'), [
                'mode' => EnumModeGenerate::Baru->value,
                'only_with_target' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame($kelompokAcuan->id, KonteksProgramKerja::kelompokAcuan()?->id);
        $this->assertSame($tahunKerja->id, KonteksProgramKerja::tahunBerjalan()?->id);
        $this->assertDatabaseCount(PenawaranProgramKerja::class, 1);
    }

    /**
     * Slot dipilih lewat tombol Ubah, dan pilihan itu berhenti di ringkasan halaman:
     * tidak ada slot yang tergeser maupun penawaran yang terbentuk sampai tombol
     * Terapkan ditekan.
     */
    public function test_page_stages_slot_selection_through_ubah_action(): void
    {
        [, $tahunKerja, $kelompokAcuan] = $this->seedAcuan();

        $kelompokAcuan->update(['is_active' => false]);
        $tahunKerja->update(['status' => EnumStatusTahunKerja::Selesai]);

        $halaman = Livewire::test(PengaturanProgramKerja::class)
            ->callAction(TestAction::make('ubahBerjalan'), [
                'kelompok_acuan_id' => $kelompokAcuan->id,
                'tahun_kerja_id' => $tahunKerja->id,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(EnumStatusTahunKerja::Selesai, $tahunKerja->refresh()->status);
        $this->assertDatabaseCount(PenawaranProgramKerja::class, 0);

        $halaman
            ->callAction(TestAction::make('terapkanBerjalan'), [
                'mode' => EnumModeGenerate::Baru->value,
                'only_with_target' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(EnumStatusTahunKerja::Berjalan, $tahunKerja->refresh()->status);
        $this->assertSame($kelompokAcuan->id, $tahunKerja->kelompok_acuan_id);
        $this->assertDatabaseCount(PenawaranProgramKerja::class, 1);
    }

    /**
     * Slot perencanaan dibentuk tanpa mengganggu slot berjalan: penawaran tahun
     * mendatang ikut terbentuk, sementara tahun berjalan tetap di tempatnya.
     */
    public function test_page_applies_perencanaan_slot_without_disturbing_the_running_year(): void
    {
        [$acuan, $tahunKerja, $kelompokAcuan] = $this->seedAcuan();

        $tahunDepan = TahunKerja::create([
            'periode_id' => $tahunKerja->periode_id,
            'name' => 'TA Mendatang',
            'tahun' => $tahunKerja->tahun + 1,
            'start_datetime' => now()->addYear(),
            'end_datetime' => now()->addYears(2),
        ]);
        $acuan->targets()->create(['tahun' => $tahunDepan->tahunTarget(), 'nilai' => '100', 'satuan' => 'persen']);

        Livewire::test(PengaturanProgramKerja::class)
            ->fillForm([
                'perencanaan_kelompok_acuan_id' => $kelompokAcuan->id,
                'perencanaan_tahun_kerja_id' => $tahunDepan->id,
                'perencanaan_referensi_tahun_kerja_id' => $tahunKerja->id,
            ])
            ->callAction(TestAction::make('terapkanPerencanaan'), [
                'mode' => EnumModeGenerate::Baru->value,
                'only_with_target' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(EnumStatusTahunKerja::Perencanaan, $tahunDepan->refresh()->status);
        $this->assertSame($tahunKerja->id, $tahunDepan->referensi_tahun_kerja_id);
        $this->assertSame($kelompokAcuan->id, $tahunDepan->kelompok_acuan_id);

        // Tahun berjalan tidak tergeser oleh penerapan slot perencanaan.
        $this->assertSame(EnumStatusTahunKerja::Berjalan, $tahunKerja->refresh()->status);
        $this->assertSame($tahunKerja->id, KonteksProgramKerja::tahunBerjalan()?->id);

        $this->assertDatabaseHas(PenawaranProgramKerja::class, ['tahun_kerja_id' => $tahunDepan->id]);
    }

    public function test_page_starts_the_planned_year_and_moves_the_old_one_to_penutupan(): void
    {
        [, $tahunKerja] = $this->seedAcuan();

        $tahunDepan = TahunKerja::create([
            'periode_id' => $tahunKerja->periode_id,
            'name' => 'TA Mendatang',
            'tahun' => $tahunKerja->tahun + 1,
            'start_datetime' => now()->addYear(),
            'end_datetime' => now()->addYears(2),
            'status' => EnumStatusTahunKerja::Perencanaan,
        ]);

        Livewire::test(PengaturanProgramKerja::class)
            ->callAction(TestAction::make('mulaiTahunPerencanaan'))
            ->assertHasNoActionErrors();

        $this->assertSame(EnumStatusTahunKerja::Berjalan, $tahunDepan->refresh()->status);
        $this->assertSame(EnumStatusTahunKerja::Penutupan, $tahunKerja->refresh()->status);
    }

    public function test_page_saves_batas_anggaran_to_the_chosen_tahun_kerja(): void
    {
        [, $tahunKerja, $kelompokAcuan] = $this->seedAcuan();

        Livewire::test(PengaturanProgramKerja::class)
            ->fillForm([
                'berjalan_kelompok_acuan_id' => $kelompokAcuan->id,
                'berjalan_tahun_kerja_id' => $tahunKerja->id,
                'berjalan_batas_anggaran' => 250_000_000,
            ])
            ->callAction(TestAction::make('terapkanBerjalan'), [
                'mode' => EnumModeGenerate::Baru->value,
                'only_with_target' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('250000000.00', $tahunKerja->fresh()->batas_anggaran);
    }

    public function test_page_saves_referensi_tahun_kerja(): void
    {
        [, $tahunKerja, $kelompokAcuan] = $this->seedAcuan();

        $referensi = TahunKerja::create([
            'periode_id' => $tahunKerja->periode_id,
            'name' => 'TA Sebelumnya',
            'tahun' => $tahunKerja->tahun - 1,
            'start_datetime' => now()->subYear(),
            'end_datetime' => now(),
        ]);

        Livewire::test(PengaturanProgramKerja::class)
            ->fillForm([
                'berjalan_kelompok_acuan_id' => $kelompokAcuan->id,
                'berjalan_tahun_kerja_id' => $tahunKerja->id,
                'berjalan_referensi_tahun_kerja_id' => $referensi->id,
            ])
            ->callAction(TestAction::make('terapkanBerjalan'), [
                'mode' => EnumModeGenerate::Baru->value,
                'only_with_target' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame($referensi->id, $tahunKerja->fresh()->referensi_tahun_kerja_id);
    }

    public function test_page_ignores_referensi_equal_to_the_selected_tahun_kerja(): void
    {
        [, $tahunKerja, $kelompokAcuan] = $this->seedAcuan();

        // Fill mengabaikan referensi yang menunjuk dirinya sendiri, disimpan sebagai null.
        Livewire::test(PengaturanProgramKerja::class)
            ->fillForm([
                'berjalan_kelompok_acuan_id' => $kelompokAcuan->id,
                'berjalan_tahun_kerja_id' => $tahunKerja->id,
                'berjalan_referensi_tahun_kerja_id' => $tahunKerja->id,
            ])
            ->callAction(TestAction::make('terapkanBerjalan'), [
                'mode' => EnumModeGenerate::Baru->value,
                'only_with_target' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertNull($tahunKerja->fresh()->referensi_tahun_kerja_id);
    }

    public function test_page_refuses_regenerate_when_penawaran_has_pengajuan(): void
    {
        [, $tahunKerja, $kelompokAcuan] = $this->seedAcuan();

        (new GeneratePenawaranFromAcuan)->handle($kelompokAcuan, $tahunKerja, EnumModeGenerate::Baru);
        $penawaran = PenawaranProgramKerja::query()->firstOrFail();
        $this->seedPengajuan($penawaran);

        Livewire::test(PengaturanProgramKerja::class)
            ->fillForm([
                'berjalan_kelompok_acuan_id' => $kelompokAcuan->id,
                'berjalan_tahun_kerja_id' => $tahunKerja->id,
            ])
            ->callAction(TestAction::make('terapkanBerjalan'), [
                'mode' => EnumModeGenerate::Ulang->value,
                'only_with_target' => true,
                'captcha_count' => 7,
                'captcha_answer' => 7,
            ]);

        $this->assertDatabaseHas(PenawaranProgramKerja::class, ['id' => $penawaran->id]);
    }

    public function test_page_rejects_regenerate_with_wrong_captcha(): void
    {
        [, $tahunKerja, $kelompokAcuan] = $this->seedAcuan();

        (new GeneratePenawaranFromAcuan)->handle($kelompokAcuan, $tahunKerja, EnumModeGenerate::Baru);
        $penawaran = PenawaranProgramKerja::query()->firstOrFail();

        Livewire::test(PengaturanProgramKerja::class)
            ->fillForm([
                'berjalan_kelompok_acuan_id' => $kelompokAcuan->id,
                'berjalan_tahun_kerja_id' => $tahunKerja->id,
            ])
            ->callAction(TestAction::make('terapkanBerjalan'), [
                'mode' => EnumModeGenerate::Ulang->value,
                'only_with_target' => true,
                'captcha_count' => 7,
                'captcha_answer' => 99,
            ]);

        $this->assertDatabaseHas(PenawaranProgramKerja::class, ['id' => $penawaran->id]);
    }
}
