<?php

namespace Tests\Feature;

use App\Enums\EnumStatusPemasukan;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Pages\PengaturanSistem;
use App\Filament\Resources\AcuanProgramKerjas\Pages\ListAcuanProgramKerjas;
use App\Filament\Resources\Pemasukans\Pages\ListPemasukans;
use App\Filament\Resources\RealisasiProgramKerjas\Pages\ListRealisasiProgramKerjas;
use App\Models\AcuanProgramKerja;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\KelompokAcuan;
use App\Models\Pemasukan;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\RealisasiProgramKerja;
use App\Models\Setting;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PengaturanSistemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filament.default_filesystem_disk'));

        $this->actingAs(User::factory()->create());
    }

    /**
     * Pengajuan realisasi wajib menyertakan dokumen proposal.
     *
     * @return array<string, mixed>
     */
    protected function dataProposal(): array
    {
        return ['proposal_path' => [UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf')]];
    }

    public function test_halaman_pengaturan_menampilkan_nilai_yang_berlaku(): void
    {
        Setting::set(Setting::TAHUN_PER_PERIODE, 4);
        Setting::set(Setting::MAKS_REALISASI_BERJALAN, 3);

        Livewire::test(PengaturanSistem::class)
            ->assertOk()
            ->assertFormSet([
                Setting::TAHUN_PER_PERIODE => 4,
                Setting::MAKS_REALISASI_BERJALAN => 3,
            ]);
    }

    public function test_pengaturan_bawaan_dipakai_saat_belum_pernah_disimpan(): void
    {
        $this->assertSame(5, Setting::tahunPerPeriode());
        $this->assertSame(2, Setting::maksRealisasiBerjalan());
    }

    public function test_dapat_menyimpan_pengaturan(): void
    {
        Livewire::test(PengaturanSistem::class)
            ->fillForm([
                Setting::TAHUN_PER_PERIODE => 6,
                Setting::MAKS_REALISASI_BERJALAN => 1,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertDatabaseHas(Setting::class, ['key' => Setting::TAHUN_PER_PERIODE, 'value' => '6']);
        $this->assertSame(6, Setting::tahunPerPeriode());
        $this->assertSame(1, Setting::maksRealisasiBerjalan());
    }

    public function test_pengaturan_wajib_diisi_angka_positif(): void
    {
        Livewire::test(PengaturanSistem::class)
            ->fillForm([
                Setting::TAHUN_PER_PERIODE => 0,
                Setting::MAKS_REALISASI_BERJALAN => null,
            ])
            ->call('save')
            ->assertHasFormErrors([
                Setting::TAHUN_PER_PERIODE => 'min',
                Setting::MAKS_REALISASI_BERJALAN => 'required',
            ]);
    }

    public function test_batas_berkas_bukti_pemasukan_tersimpan_dan_langsung_berlaku(): void
    {
        Livewire::test(PengaturanSistem::class)
            ->assertFormSet([Setting::MAKS_BUKTI_PEMASUKAN => 3])
            ->fillForm([Setting::MAKS_BUKTI_PEMASUKAN => 1])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertDatabaseHas(Setting::class, ['key' => Setting::MAKS_BUKTI_PEMASUKAN, 'value' => '1']);
        $this->assertSame(1, Setting::maksBuktiPemasukan());

        $unit = $this->seedUnitKerja();
        auth()->user()->update(['unit_kerja_id' => $unit->id]);

        $pemasukan = Pemasukan::factory()
            ->berstatus(EnumStatusPemasukan::MenungguBukti, auth()->user())
            ->create(['unit_kerja_id' => $unit->id, 'user_id' => auth()->id()]);

        // Batas baru langsung dipatuhi aksi unggah: dua berkas ditolak, satu diterima.
        Livewire::test(ListPemasukans::class)
            ->callAction(TestAction::make('unggahBuktiPemasukan')->table($pemasukan), [
                'bukti_path' => [
                    UploadedFile::fake()->create('bukti-1.pdf', 20, 'application/pdf'),
                    UploadedFile::fake()->create('bukti-2.pdf', 20, 'application/pdf'),
                ],
            ])
            ->assertHasActionErrors(['bukti_path' => 'max']);

        $this->assertSame(EnumStatusPemasukan::MenungguBukti, $pemasukan->refresh()->status);

        Livewire::test(ListPemasukans::class)
            ->callAction(TestAction::make('unggahBuktiPemasukan')->table($pemasukan), [
                'bukti_path' => [UploadedFile::fake()->create('bukti-1.pdf', 20, 'application/pdf')],
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(EnumStatusPemasukan::Valid, $pemasukan->refresh()->status);
    }

    public function test_tabel_acuan_menampilkan_satu_kolom_target_per_tahun(): void
    {
        Setting::set(Setting::TAHUN_PER_PERIODE, 3);

        $kelompok = KelompokAcuan::create([
            'name' => 'Program Kerja 2025 - 2027',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2027,
            'is_active' => true,
        ]);

        $acuan = AcuanProgramKerja::create([
            'kelompok_acuan_id' => $kelompok->id,
            'unit_kerja_id' => UnitKerja::create(['name' => 'Fakultas Teknik'])->id,
            'bidang_id' => Bidang::create(['code' => 'B1', 'name' => 'Akademik'])->id,
            'kategori_id' => Kategori::create(['code' => 'K1', 'name' => 'Pendidikan'])->id,
            'program_id' => Program::create(['name' => 'Tridharma'])->id,
            'name' => 'Peningkatan Mutu',
            'is_active' => true,
        ]);

        $acuan->targets()->create(['tahun' => 2025, 'nilai' => '80', 'satuan' => 'persen']);
        $acuan->targets()->create(['tahun' => 2027, 'nilai' => '100', 'satuan' => 'persen']);

        Livewire::test(ListAcuanProgramKerjas::class)
            ->assertCanSeeTableRecords([$acuan])
            ->assertTableColumnExists('target_2025')
            ->assertTableColumnExists('target_2026')
            ->assertTableColumnExists('target_2027')
            ->assertTableColumnStateSet('target_2025', '80 persen', $acuan)
            ->assertTableColumnStateSet('target_2026', null, $acuan)
            ->assertTableColumnStateSet('target_2027', '100 persen', $acuan);
    }

    public function test_realisasi_melebihi_kuota_berjalan_tidak_dapat_diajukan(): void
    {
        Setting::set(Setting::MAKS_REALISASI_BERJALAN, 2);

        $unit = $this->seedUnitKerja();
        auth()->user()->update(['unit_kerja_id' => $unit->id]);

        $berjalanSatu = $this->seedRealisasi($unit, EnumStatusRealisasi::Diajukan);
        $berjalanDua = $this->seedRealisasi($unit, EnumStatusRealisasi::VerifikasiRektor);
        $draft = $this->seedRealisasi($unit, EnumStatusRealisasi::Draft);

        Livewire::test(ListRealisasiProgramKerjas::class)
            ->callAction(TestAction::make('ajukanRealisasi')->table($draft), $this->dataProposal())
            ->assertNotified('Kuota realisasi berjalan sudah penuh');

        $this->assertSame(EnumStatusRealisasi::Draft, $draft->refresh()->status);

        // Satu realisasi selesai membebaskan kuotanya, sehingga draf dapat diajukan.
        $berjalanSatu->update(['status' => EnumStatusRealisasi::Selesai]);

        Livewire::test(ListRealisasiProgramKerjas::class)
            ->callAction(TestAction::make('ajukanRealisasi')->table($draft), $this->dataProposal())
            ->assertNotified('Realisasi berhasil diajukan');

        $this->assertSame(EnumStatusRealisasi::Diajukan, $draft->refresh()->status);
        $this->assertSame(EnumStatusRealisasi::VerifikasiRektor, $berjalanDua->refresh()->status);
    }

    public function test_kuota_dihitung_per_unit_kerja(): void
    {
        Setting::set(Setting::MAKS_REALISASI_BERJALAN, 1);

        $unitPenuh = $this->seedUnitKerja('Fakultas Teknik');
        $unitLain = $this->seedUnitKerja('Fakultas Kesehatan');
        auth()->user()->update(['unit_kerja_id' => $unitLain->id]);

        $this->seedRealisasi($unitPenuh, EnumStatusRealisasi::Diajukan);
        $draftUnitLain = $this->seedRealisasi($unitLain, EnumStatusRealisasi::Draft);

        $this->assertSame(0, RealisasiProgramKerja::sisaKuotaBerjalan($unitPenuh->id));
        $this->assertSame(1, RealisasiProgramKerja::sisaKuotaBerjalan($unitLain->id));

        Livewire::test(ListRealisasiProgramKerjas::class)
            ->callAction(TestAction::make('ajukanRealisasi')->table($draftUnitLain), $this->dataProposal())
            ->assertNotified('Realisasi berhasil diajukan');

        $this->assertSame(EnumStatusRealisasi::Diajukan, $draftUnitLain->refresh()->status);
    }

    protected function seedUnitKerja(string $name = 'Fakultas Teknik'): UnitKerja
    {
        return UnitKerja::create(['name' => $name]);
    }

    protected function seedRealisasi(UnitKerja $unit, EnumStatusRealisasi $status): RealisasiProgramKerja
    {
        $periode = Periode::firstOrCreate(
            ['name' => 'Periode 2026'],
            ['start_datetime' => now(), 'end_datetime' => now()->addYear()],
        );
        $tahunKerja = TahunKerja::firstOrCreate(
            ['name' => 'TA 2026'],
            ['periode_id' => $periode->id, 'start_datetime' => now(), 'end_datetime' => now()->addYear(), 'status' => EnumStatusTahunKerja::Berjalan],
        );

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
