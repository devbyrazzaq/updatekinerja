<?php

namespace Tests\Feature;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Pages\PenyelesaianTahunLalu;
use App\Filament\Resources\DaftarProgramKerjas\Pages\ListDaftarProgramKerjas;
use App\Filament\Resources\JadwalPencairans\Pages\ListJadwalPencairans;
use App\Filament\Resources\PaguAnggarans\Pages\ListPaguAnggarans;
use App\Filament\Resources\PenawaranProgramKerjas\Pages\ListPenawaranProgramKerjas;
use App\Filament\Resources\PengajuanProgramKerjas\Pages\ListPengajuanProgramKerjas;
use App\Filament\Resources\PerencanaanDaftarProgramKerjas\Pages\ListPerencanaanDaftarProgramKerjas;
use App\Filament\Resources\PerencanaanDaftarProgramKerjas\PerencanaanDaftarProgramKerjaResource;
use App\Filament\Resources\PerencanaanPengajuanProgramKerjas\Pages\ListPerencanaanPengajuanProgramKerjas;
use App\Filament\Resources\PerencanaanPengajuanProgramKerjas\PerencanaanPengajuanProgramKerjaResource;
use App\Filament\Resources\PerencanaanVerifikasiPengajuans\Pages\ListPerencanaanVerifikasiPengajuans;
use App\Filament\Resources\PerencanaanVerifikasiPengajuans\PerencanaanVerifikasiPengajuanResource;
use App\Filament\Resources\RealisasiProgramKerjas\Pages\ListRealisasiProgramKerjas;
use App\Filament\Resources\RealisasiProgramKerjas\RealisasiProgramKerjaResource;
use App\Filament\Resources\VerifikasiPengajuans\Pages\ListVerifikasiPengajuans;
use App\Filament\Resources\VerifikasiRektors\VerifikasiRektorResource;
use App\Models\Bidang;
use App\Models\JadwalPencairan;
use App\Models\Kategori;
use App\Models\PaguAnggaran;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\RealisasiProgramKerja;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\KonteksProgramKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tahun kerja berjalan dan tahun kerja perencanaan hidup berdampingan: fase
 * perencanaan memuat keduanya, fase pelaksanaan tidak pernah menyentuh tahun yang
 * masih direncanakan.
 */
class KonteksDuaTahunTest extends TestCase
{
    use RefreshDatabase;

    protected TahunKerja $berjalan;

    protected TahunKerja $perencanaan;

    protected UnitKerja $unit;

    protected function setUp(): void
    {
        parent::setUp();

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

        // Data scope menyaring pengajuan & realisasi ke unit kerja pengguna, sehingga
        // pengguna uji harus bernaung di unit yang datanya dibuat di sini.
        $this->actingAs(User::factory()->create(['unit_kerja_id' => $this->unit->id]));
    }

    public function test_konteks_memisahkan_tahun_perencanaan_dari_tahun_pelaksanaan(): void
    {
        $this->assertSame(
            [$this->berjalan->id, $this->perencanaan->id],
            KonteksProgramKerja::tahunPerencanaanIds(),
        );

        $this->assertSame([$this->berjalan->id], KonteksProgramKerja::tahunPelaksanaanIds());
    }

    public function test_pagu_anggaran_memuat_kedua_tahun(): void
    {
        $paguBerjalan = $this->pagu($this->berjalan, 100_000_000);
        $paguPerencanaan = $this->pagu($this->perencanaan, 120_000_000);

        Livewire::test(ListPaguAnggarans::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$paguBerjalan])
            ->filterTable('tahun_kerja_id', $this->perencanaan->id)
            ->assertCanSeeTableRecords([$paguPerencanaan]);
    }

    public function test_pagu_anggaran_bawaannya_menyaring_tahun_berjalan(): void
    {
        $paguBerjalan = $this->pagu($this->berjalan, 100_000_000);
        $paguPerencanaan = $this->pagu($this->perencanaan, 120_000_000);

        Livewire::test(ListPaguAnggarans::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$paguBerjalan])
            ->assertCanNotSeeTableRecords([$paguPerencanaan]);
    }

    public function test_penawaran_memuat_kedua_tahun(): void
    {
        $penawaranBerjalan = $this->penawaran($this->berjalan);
        $penawaranPerencanaan = $this->penawaran($this->perencanaan);

        Livewire::test(ListPenawaranProgramKerjas::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$penawaranBerjalan, $penawaranPerencanaan]);
    }

    /**
     * Daftar Program Kerja punya dua menu: grup Pelaksanaan menggarap tahun Berjalan,
     * grup Perencanaan menggarap tahun Perencanaan. Keduanya tidak pernah beririsan.
     */
    public function test_daftar_program_kerja_dipisah_per_slot_tahun(): void
    {
        $penawaranBerjalan = $this->penawaran($this->berjalan);
        $penawaranPerencanaan = $this->penawaran($this->perencanaan);

        Livewire::test(ListDaftarProgramKerjas::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$penawaranBerjalan])
            ->assertCanNotSeeTableRecords([$penawaranPerencanaan]);

        Livewire::test(ListPerencanaanDaftarProgramKerjas::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$penawaranPerencanaan])
            ->assertCanNotSeeTableRecords([$penawaranBerjalan]);
    }

    public function test_pengajuan_dipisah_per_slot_tahun(): void
    {
        $pengajuanBerjalan = $this->pengajuan($this->penawaran($this->berjalan), EnumStatusPengajuan::Diajukan);
        $pengajuanPerencanaan = $this->pengajuan($this->penawaran($this->perencanaan), EnumStatusPengajuan::Diajukan);

        Livewire::test(ListPengajuanProgramKerjas::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$pengajuanBerjalan])
            ->assertCanNotSeeTableRecords([$pengajuanPerencanaan]);

        Livewire::test(ListPerencanaanPengajuanProgramKerjas::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$pengajuanPerencanaan])
            ->assertCanNotSeeTableRecords([$pengajuanBerjalan]);
    }

    public function test_verifikasi_pengajuan_dipisah_per_slot_tahun(): void
    {
        $pengajuanBerjalan = $this->pengajuan($this->penawaran($this->berjalan), EnumStatusPengajuan::Diajukan);
        $pengajuanPerencanaan = $this->pengajuan($this->penawaran($this->perencanaan), EnumStatusPengajuan::Diajukan);

        Livewire::test(ListVerifikasiPengajuans::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$pengajuanBerjalan])
            ->assertCanNotSeeTableRecords([$pengajuanPerencanaan]);

        Livewire::test(ListPerencanaanVerifikasiPengajuans::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$pengajuanPerencanaan])
            ->assertCanNotSeeTableRecords([$pengajuanBerjalan]);
    }

    /**
     * Tanpa tahun yang direncanakan, menu grup Perencanaan tidak boleh jatuh kembali
     * menampilkan seluruh data — ia memang tidak punya apa pun untuk ditampilkan, dan
     * tautannya hilang dari sidebar.
     */
    public function test_menu_perencanaan_kosong_saat_tidak_ada_tahun_perencanaan(): void
    {
        $penawaranBerjalan = $this->penawaran($this->berjalan);
        $penawaranPerencanaan = $this->penawaran($this->perencanaan);

        $this->perencanaan->update(['status' => EnumStatusTahunKerja::Selesai]);

        $this->assertFalse(PerencanaanDaftarProgramKerjaResource::shouldRegisterNavigation());
        $this->assertFalse(PerencanaanPengajuanProgramKerjaResource::shouldRegisterNavigation());
        $this->assertFalse(PerencanaanVerifikasiPengajuanResource::shouldRegisterNavigation());

        Livewire::test(ListPerencanaanDaftarProgramKerjas::class)
            ->assertOk()
            ->assertCanNotSeeTableRecords([$penawaranBerjalan, $penawaranPerencanaan]);
    }

    /**
     * Realisasi tahun perencanaan tidak sekadar tersembunyi di balik penyaring
     * bawaan — ia tidak pernah masuk ke kueri resource-nya sama sekali.
     */
    public function test_realisasi_dan_verifikasinya_tidak_pernah_menjangkau_tahun_perencanaan(): void
    {
        $realisasiBerjalan = $this->realisasi($this->berjalan, EnumStatusRealisasi::VerifikasiRektor);
        $realisasiPerencanaan = $this->realisasi($this->perencanaan, EnumStatusRealisasi::VerifikasiRektor);

        $terjangkau = RealisasiProgramKerjaResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($realisasiBerjalan->id, $terjangkau);
        $this->assertNotContains($realisasiPerencanaan->id, $terjangkau);

        $terjangkauVerifikasi = VerifikasiRektorResource::getEloquentQuery()->pluck('id')->all();

        $this->assertNotContains($realisasiPerencanaan->id, $terjangkauVerifikasi);

        Livewire::test(ListRealisasiProgramKerjas::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$realisasiBerjalan])
            ->assertCanNotSeeTableRecords([$realisasiPerencanaan]);
    }

    public function test_jadwal_pencairan_tidak_memuat_tahun_perencanaan(): void
    {
        $jadwalBerjalan = JadwalPencairan::create([
            'tahun_kerja_id' => $this->berjalan->id,
            'name' => 'Pencairan Januari',
            'tanggal_pencairan' => now()->addWeek(),
        ]);
        $jadwalPerencanaan = JadwalPencairan::create([
            'tahun_kerja_id' => $this->perencanaan->id,
            'name' => 'Pencairan 2027',
            'tanggal_pencairan' => now()->addYear(),
        ]);

        Livewire::test(ListJadwalPencairans::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$jadwalBerjalan])
            ->assertCanNotSeeTableRecords([$jadwalPerencanaan]);
    }

    /**
     * Begitu tahun kerja ditinggalkan, realisasinya lenyap dari menu harian —
     * termasuk yang belum tuntas — supaya angka tahun baru tidak tercampur data
     * tahun lalu. Tunggakannya pindah ke halaman Penyelesaian Tahun Lalu.
     */
    public function test_tahun_penutupan_tidak_lagi_terlihat_di_fase_pelaksanaan(): void
    {
        $realisasi = $this->realisasi($this->berjalan, EnumStatusRealisasi::MenungguLaporan);

        $this->berjalan->update(['status' => EnumStatusTahunKerja::Penutupan]);

        $this->assertSame([], KonteksProgramKerja::tahunPelaksanaanIds());
        $this->assertSame([$this->berjalan->id], KonteksProgramKerja::tahunPenutupanIds());

        // Slot berjalan yang kosong ditutup rapat, tanpa menunggu tahun pengganti:
        // itulah yang membuat tombol Akhiri Tahun Kerja benar-benar mengunci.
        $this->assertNotContains(
            $realisasi->id,
            RealisasiProgramKerjaResource::getEloquentQuery()->pluck('id')->all(),
        );

        $this->assertNotContains(
            $realisasi->id,
            VerifikasiRektorResource::getEloquentQuery()->pluck('id')->all(),
        );

        Livewire::test(ListRealisasiProgramKerjas::class)
            ->assertOk()
            ->assertCanNotSeeTableRecords([$realisasi]);
    }

    /**
     * Kelonggaran "belum dikonfigurasi" hanya berlaku untuk instalasi yang memang
     * belum pernah menjalankan tahun kerja, supaya konteks pertama masih bisa
     * dibentuk dan data lama tetap terjangkau.
     */
    public function test_instalasi_yang_belum_pernah_dikonfigurasi_tidak_menyaring_pelaksanaan(): void
    {
        $realisasi = $this->realisasi($this->berjalan, EnumStatusRealisasi::MenungguLaporan);

        $this->berjalan->update(['status' => EnumStatusTahunKerja::Selesai]);
        $this->perencanaan->update(['status' => EnumStatusTahunKerja::Selesai]);

        $this->assertFalse(KonteksProgramKerja::pernahDikonfigurasi());
        $this->assertSame([], KonteksProgramKerja::tahunPelaksanaanIds());

        $this->assertContains(
            $realisasi->id,
            RealisasiProgramKerjaResource::getEloquentQuery()->pluck('id')->all(),
        );
    }

    /**
     * Tahun kerja yang sudah dikunci kembali berstatus Selesai — status yang sama
     * dengan tahun yang belum pernah dijalankan. Stempel penguncianlah yang menjaga
     * fase pelaksanaan tetap tertutup, bukan statusnya.
     */
    public function test_tahun_yang_sudah_dikunci_tetap_menutup_fase_pelaksanaan(): void
    {
        $realisasi = $this->realisasi($this->berjalan, EnumStatusRealisasi::Selesai);

        $this->perencanaan->update(['status' => EnumStatusTahunKerja::Selesai]);
        $this->berjalan->update([
            'status' => EnumStatusTahunKerja::Selesai,
            'ditutup_pada' => now(),
            'dikunci_pada' => now(),
        ]);

        $this->assertTrue(KonteksProgramKerja::pernahDikonfigurasi());
        $this->assertSame([], KonteksProgramKerja::tahunPelaksanaanIds());

        $this->assertNotContains(
            $realisasi->id,
            RealisasiProgramKerjaResource::getEloquentQuery()->pluck('id')->all(),
        );
    }

    /**
     * Tunggakan tahun Penutupan tetap terjangkau, tetapi hanya lewat halaman
     * Penyelesaian Tahun Lalu.
     */
    public function test_tunggakan_tahun_penutupan_muncul_di_halaman_penyelesaian(): void
    {
        $tunggakan = $this->realisasi($this->berjalan, EnumStatusRealisasi::MenungguLaporan);
        $selesai = $this->realisasi($this->berjalan, EnumStatusRealisasi::Selesai);

        $this->berjalan->update(['status' => EnumStatusTahunKerja::Penutupan]);

        Livewire::test(PenyelesaianTahunLalu::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$tunggakan])
            ->assertCanNotSeeTableRecords([$selesai]);
    }

    protected function pagu(TahunKerja $tahunKerja, int $amount): PaguAnggaran
    {
        return PaguAnggaran::create([
            'tahun_kerja_id' => $tahunKerja->id,
            'unit_kerja_id' => $this->unit->id,
            'amount' => $amount,
        ]);
    }

    protected function penawaran(TahunKerja $tahunKerja): PenawaranProgramKerja
    {
        return PenawaranProgramKerja::create([
            'tahun_kerja_id' => $tahunKerja->id,
            'unit_kerja_id' => $this->unit->id,
            'bidang_id' => Bidang::firstOrCreate(['code' => 'B1'], ['name' => 'Akademik'])->id,
            'kategori_id' => Kategori::firstOrCreate(['code' => 'K1'], ['name' => 'Pendidikan'])->id,
            'program_id' => Program::firstOrCreate(['name' => 'Tridharma'])->id,
            'name' => 'Kegiatan '.fake()->unique()->word(),
            'is_active' => true,
        ]);
    }

    protected function pengajuan(PenawaranProgramKerja $penawaran, EnumStatusPengajuan $status): PengajuanProgramKerja
    {
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
        $pengajuan = $this->pengajuan($this->penawaran($tahunKerja), EnumStatusPengajuan::Diterima);

        return RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'name' => 'Realisasi '.fake()->unique()->word(),
            'anggaran_digunakan' => 1_000_000,
            'status' => $status,
        ]);
    }
}
