<?php

namespace Tests\Feature;

use App\Enums\EnumJenisMutasiAnggaran;
use App\Enums\EnumStatusAnggaran;
use App\Enums\EnumStatusPemasukan;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusPenyelesaianAnggaran;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Enums\EnumSumberPemasukan;
use App\Filament\Pages\BukuAnggaran as BukuAnggaranPage;
use App\Filament\Pages\BukuAnggaranKeseluruhan as BukuAnggaranKeseluruhanPage;
use App\Filament\Pages\Widgets\BukuAnggaranOverview;
use App\Filament\Pages\Widgets\MutasiAnggaranChart;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\PaguAnggaran;
use App\Models\Pemasukan;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\RealisasiProgramKerja;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\BukuAnggaran;
use App\Services\BukuAnggaranGabungan;
use App\Services\MutasiAnggaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Buku Anggaran: mutasi anggaran unit kerja bergaya buku kas. Anggaran keluar saat
 * dicairkan (bukan saat pengajuan dibuat), dan pemasukan tercatat di kolomnya sendiri
 * tanpa menggerakkan saldo.
 */
class BukuAnggaranTest extends TestCase
{
    use RefreshDatabase;

    private TahunKerja $tahunKerja;

    private UnitKerja $unitA;

    private UnitKerja $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        $periode = Periode::create(['name' => 'P1', 'start_datetime' => Carbon::create(2026, 1, 1), 'end_datetime' => Carbon::create(2026, 12, 31)]);
        $this->tahunKerja = TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA 2026',
            'start_datetime' => Carbon::create(2026, 1, 1),
            'end_datetime' => Carbon::create(2026, 12, 31),
            'status' => EnumStatusTahunKerja::Berjalan,
        ]);

        $this->unitA = UnitKerja::create(['name' => 'Unit A']);
        $this->unitB = UnitKerja::create(['name' => 'Unit B']);

        $user = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);
        $user->givePermissionTo(Permission::findOrCreate('bypass_data_scope', 'web'));

        $this->actingAs($user);
    }

    public function test_pagu_menjadi_kredit_pembuka_buku(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);

        $mutasi = BukuAnggaran::untukUnit($this->unitA->id)->mutasi();

        $this->assertCount(1, $mutasi);
        $this->assertSame(EnumJenisMutasiAnggaran::PaguDitetapkan, $mutasi->first()->jenis);
        $this->assertSame(20_000_000.0, $mutasi->first()->kredit());
        $this->assertSame(20_000_000.0, $mutasi->first()->saldo);
    }

    public function test_pengajuan_yang_belum_cair_tidak_masuk_buku(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);

        // Draf, sedang diverifikasi, dan sudah diterima — semuanya belum dicairkan.
        $draf = $this->seedPengajuan($this->unitA, 5_000_000, EnumStatusPengajuan::Draft);
        $diterima = $this->seedPengajuan($this->unitA, 8_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($draf, 5_000_000, EnumStatusRealisasi::Draft);
        $this->seedRealisasi($diterima, 8_000_000, EnumStatusRealisasi::VerifikasiKeuangan);

        $buku = BukuAnggaran::untukUnit($this->unitA->id);

        $this->assertCount(1, $buku->mutasi());
        $this->assertSame(0.0, $buku->ringkasan()['debit']);
        $this->assertSame(20_000_000.0, $buku->ringkasan()['saldo']);
    }

    public function test_anggaran_yang_dicairkan_menjadi_debit(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 8_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuan, 8_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'nominal_disetujui' => 8_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
        ]);

        $buku = BukuAnggaran::untukUnit($this->unitA->id);
        $pencairan = $buku->mutasi()->last();

        $this->assertSame(EnumJenisMutasiAnggaran::AnggaranDicairkan, $pencairan->jenis);
        $this->assertSame(8_000_000.0, $pencairan->debit());
        $this->assertSame(12_000_000.0, $pencairan->saldo);
        $this->assertSame(12_000_000.0, $buku->ringkasan()['saldo']);
    }

    public function test_sisa_dikembalikan_menambah_dan_kekurangan_dilunasi_mengurangi_saldo(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 15_000_000, EnumStatusPengajuan::Diterima);

        // Cair 10jt lalu 2jt sisanya dikembalikan.
        $this->seedRealisasi($pengajuan, 8_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 10_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
            'status_anggaran' => EnumStatusAnggaran::Sisa,
            'nominal_selisih_anggaran' => 2_000_000,
            'status_penyelesaian_anggaran' => EnumStatusPenyelesaianAnggaran::Dikembalikan,
            'penyelesaian_anggaran_at' => Carbon::create(2026, 4, 1),
        ]);

        // Cair 5jt lalu kekurangan 1jt ditalangi unit kerja.
        $this->seedRealisasi($pengajuan, 6_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 5_000_000,
            'dicairkan_at' => Carbon::create(2026, 5, 1),
            'status_anggaran' => EnumStatusAnggaran::Kurang,
            'nominal_selisih_anggaran' => 1_000_000,
            'status_penyelesaian_anggaran' => EnumStatusPenyelesaianAnggaran::Dilunasi,
            'penyelesaian_anggaran_at' => Carbon::create(2026, 6, 1),
        ]);

        $ringkasan = BukuAnggaran::untukUnit($this->unitA->id)->ringkasan();

        // Kredit: pagu 20jt + sisa 2jt. Debit: cair 10jt + cair 5jt + kekurangan 1jt.
        $this->assertSame(22_000_000.0, $ringkasan['kredit']);
        $this->assertSame(16_000_000.0, $ringkasan['debit']);
        $this->assertSame(6_000_000.0, $ringkasan['saldo']);
    }

    public function test_selisih_yang_masih_menunggu_biro_keuangan_belum_menggerakkan_buku(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuan, 8_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 10_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
            'status_anggaran' => EnumStatusAnggaran::Sisa,
            'nominal_selisih_anggaran' => 2_000_000,
            'status_penyelesaian_anggaran' => EnumStatusPenyelesaianAnggaran::Menunggu,
        ]);

        $mutasi = BukuAnggaran::untukUnit($this->unitA->id)->mutasi();

        $this->assertCount(2, $mutasi);
        $this->assertSame(10_000_000.0, BukuAnggaran::untukUnit($this->unitA->id)->ringkasan()['saldo']);
    }

    public function test_pemasukan_tercatat_terpisah_tanpa_mengubah_saldo(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);

        Pemasukan::create([
            'unit_kerja_id' => $this->unitA->id,
            'sumber' => EnumSumberPemasukan::Realisasi,
            'rincian_kegiatan' => 'Seminar nasional',
            'tanggal_pelaksanaan' => Carbon::create(2026, 2, 20),
            'nominal_pendapatan' => 3_000_000,
            'status' => EnumStatusPemasukan::Valid,
        ]);

        $buku = BukuAnggaran::untukUnit($this->unitA->id);
        $pemasukan = $buku->mutasi()->last();
        $ringkasan = $buku->ringkasan();

        $this->assertSame(EnumJenisMutasiAnggaran::Pemasukan, $pemasukan->jenis);
        $this->assertSame(3_000_000.0, $pemasukan->pemasukan());
        $this->assertSame(0.0, $pemasukan->debit());
        $this->assertSame(0.0, $pemasukan->kredit());
        // Saldo tetap sebesar pagu: pemasukan belum menambah plafon anggaran.
        $this->assertSame(20_000_000.0, $pemasukan->saldo);
        $this->assertSame(3_000_000.0, $ringkasan['pemasukan']);
        $this->assertSame(20_000_000.0, $ringkasan['saldo']);
    }

    public function test_buku_terurut_waktu_dan_saldo_berjalan_akumulatif(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuan, 4_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'nominal_disetujui' => 4_000_000,
            'dicairkan_at' => Carbon::create(2026, 5, 10),
        ]);
        $this->seedRealisasi($pengajuan, 3_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'nominal_disetujui' => 3_000_000,
            'dicairkan_at' => Carbon::create(2026, 2, 10),
        ]);

        $mutasi = BukuAnggaran::untukUnit($this->unitA->id)->mutasi();

        $this->assertSame(
            [20_000_000.0, 17_000_000.0, 13_000_000.0],
            $mutasi->map(fn (MutasiAnggaran $baris): float => $baris->saldo)->all(),
        );
    }

    public function test_buku_tidak_bocor_ke_unit_kerja_lain(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $this->seedPagu($this->unitB, 9_000_000);

        $pengajuanB = $this->seedPengajuan($this->unitB, 5_000_000, EnumStatusPengajuan::Diterima);
        $this->seedRealisasi($pengajuanB, 5_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'nominal_disetujui' => 5_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
        ]);

        $this->assertSame(20_000_000.0, BukuAnggaran::untukUnit($this->unitA->id)->ringkasan()['saldo']);
        $this->assertSame(4_000_000.0, BukuAnggaran::untukUnit($this->unitB->id)->ringkasan()['saldo']);
    }

    public function test_mutasi_satu_realisasi_hanya_berisi_baris_miliknya(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);

        $realisasi = $this->seedRealisasi($pengajuan, 8_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 10_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
            'status_anggaran' => EnumStatusAnggaran::Sisa,
            'nominal_selisih_anggaran' => 2_000_000,
            'status_penyelesaian_anggaran' => EnumStatusPenyelesaianAnggaran::Dikembalikan,
            'penyelesaian_anggaran_at' => Carbon::create(2026, 4, 1),
        ]);

        $mutasiRealisasi = BukuAnggaran::untukRecord($realisasi);
        $mutasiPengajuan = BukuAnggaran::untukRecord($pengajuan);

        $this->assertSame(
            [EnumJenisMutasiAnggaran::AnggaranDicairkan, EnumJenisMutasiAnggaran::SisaDikembalikan],
            $mutasiRealisasi->map(fn (MutasiAnggaran $baris): EnumJenisMutasiAnggaran => $baris->jenis)->all(),
        );
        // Pengajuan membawa baris seluruh realisasi di bawahnya, tanpa baris pagu.
        $this->assertCount(2, $mutasiPengajuan);
    }

    public function test_ringkasan_memuat_total_pagu_terpisah_dari_kredit(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuan, 8_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 10_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
            'status_anggaran' => EnumStatusAnggaran::Sisa,
            'nominal_selisih_anggaran' => 2_000_000,
            'status_penyelesaian_anggaran' => EnumStatusPenyelesaianAnggaran::Dikembalikan,
            'penyelesaian_anggaran_at' => Carbon::create(2026, 4, 1),
        ]);

        $ringkasan = BukuAnggaran::untukUnit($this->unitA->id)->ringkasan();

        // Kredit memuat pagu 20jt + sisa dikembalikan 2jt, pagunya sendiri tetap 20jt.
        $this->assertSame(22_000_000.0, $ringkasan['kredit']);
        $this->assertSame(20_000_000.0, $ringkasan['pagu']);
    }

    public function test_buku_keseluruhan_saldonya_total_pagu_yang_berkurang(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $this->seedPagu($this->unitB, 9_000_000);

        $pengajuanB = $this->seedPengajuan($this->unitB, 5_000_000, EnumStatusPengajuan::Diterima);
        $this->seedRealisasi($pengajuanB, 5_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'nominal_disetujui' => 5_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
        ]);

        $gabungan = BukuAnggaranGabungan::untukUnits([
            $this->unitA->id => $this->unitA->name,
            $this->unitB->id => $this->unitB->name,
        ]);

        $mutasi = $gabungan->mutasi();

        // Pagu seluruh unit jadi saldo pembuka (20jt lalu 29jt), pencairan menguranginya.
        $this->assertSame(
            [$this->unitA->id, $this->unitB->id, $this->unitB->id],
            $mutasi->map(fn (MutasiAnggaran $baris): ?int => $baris->unitKerjaId)->all(),
        );
        $this->assertSame(
            [20_000_000.0, 29_000_000.0, 24_000_000.0],
            $mutasi->map(fn (MutasiAnggaran $baris): float => $baris->saldo)->all(),
        );

        $ringkasan = $gabungan->ringkasan();

        $this->assertSame(29_000_000.0, $ringkasan['pagu']);
        $this->assertSame(5_000_000.0, $ringkasan['debit']);
        $this->assertSame(24_000_000.0, $ringkasan['saldo']);
    }

    public function test_buku_keseluruhan_mutasi_lintas_unit_urut_waktu(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $this->seedPagu($this->unitB, 9_000_000);

        $pengajuanA = $this->seedPengajuan($this->unitA, 6_000_000, EnumStatusPengajuan::Diterima);
        $this->seedRealisasi($pengajuanA, 6_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'nominal_disetujui' => 6_000_000,
            'dicairkan_at' => Carbon::create(2026, 5, 1),
        ]);

        $pengajuanB = $this->seedPengajuan($this->unitB, 4_000_000, EnumStatusPengajuan::Diterima);
        $this->seedRealisasi($pengajuanB, 4_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'nominal_disetujui' => 4_000_000,
            'dicairkan_at' => Carbon::create(2026, 2, 1),
        ]);

        $mutasi = BukuAnggaranGabungan::untukUnits([
            $this->unitA->id => $this->unitA->name,
            $this->unitB->id => $this->unitB->name,
        ])->mutasi();

        // Setelah baris pagu, pencairan Unit B (Februari) mendahului Unit A (Mei).
        $this->assertSame(
            [$this->unitA->id, $this->unitB->id, $this->unitB->id, $this->unitA->id],
            $mutasi->map(fn (MutasiAnggaran $baris): ?int => $baris->unitKerjaId)->all(),
        );
        $this->assertSame(
            [20_000_000.0, 29_000_000.0, 25_000_000.0, 19_000_000.0],
            $mutasi->map(fn (MutasiAnggaran $baris): float => $baris->saldo)->all(),
        );
    }

    public function test_halaman_keseluruhan_menampilkan_seluruh_unit_kerja(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $this->seedPagu($this->unitB, 7_000_000);

        Livewire::test(BukuAnggaranKeseluruhanPage::class)
            ->assertOk()
            // Nama unit kerja tampil sebagai deskripsi baris keterangan.
            ->assertSee('Unit A')
            ->assertSee('Unit B')
            // Kartu ringkasan menjumlahkan pagu kedua unit.
            ->assertSee('Rp 27.000.000');
    }

    public function test_halaman_keseluruhan_mengikuti_scope_data_pengguna(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $this->seedPagu($this->unitB, 7_000_000);

        // Pengguna tanpa bypass_data_scope yang hanya berhak atas Unit A.
        $pengguna = User::factory()->create(['unit_kerja_id' => $this->unitA->id]);
        $pengguna->givePermissionTo(Permission::findOrCreate('view_page_buku_anggaran_keseluruhan', 'web'));

        $this->actingAs($pengguna);

        Livewire::test(BukuAnggaranKeseluruhanPage::class)
            ->assertOk()
            ->assertSee('Rp 20.000.000')
            ->assertDontSee('Unit B')
            ->assertDontSee('Rp 7.000.000');
    }

    public function test_halaman_menampilkan_buku_unit_kerja_pengguna(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);
        $this->seedRealisasi($pengajuan, 8_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'nominal_disetujui' => 8_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
        ]);

        Livewire::test(BukuAnggaranPage::class)
            ->assertOk()
            ->assertSet('unitKerjaId', $this->unitA->id)
            ->assertSee('Pagu Anggaran')
            ->assertSee('Anggaran Dicairkan')
            // Saldo akhir 20jt − 8jt.
            ->assertSee('Rp 12.000.000');
    }

    public function test_halaman_mengikuti_unit_kerja_yang_dipilih(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $this->seedPagu($this->unitB, 7_000_000);

        Livewire::test(BukuAnggaranPage::class)
            ->set('data.unitKerjaId', $this->unitB->id)
            ->assertSet('unitKerjaId', $this->unitB->id)
            ->assertSee('Rp 7.000.000');
    }

    public function test_halaman_keseluruhan_mengikuti_tahun_kerja_yang_dipilih(): void
    {
        $tahunLalu = $this->seedTahunKerjaLain();

        $this->seedPagu($this->unitA, 20_000_000);
        $this->seedPagu($this->unitB, 7_000_000);
        $this->seedPagu($this->unitA, 4_000_000, $tahunLalu);

        Livewire::test(BukuAnggaranKeseluruhanPage::class)
            ->assertOk()
            ->assertSet('tahunKerjaId', $this->tahunKerja->id)
            // Total pagu kedua unit pada tahun aktif.
            ->assertSee('Rp 27.000.000')
            ->set('data.tahunKerjaId', $tahunLalu->id)
            ->assertSet('tahunKerjaId', $tahunLalu->id)
            ->assertSee('TA 2025')
            ->assertSee('Rp 4.000.000')
            ->assertDontSee('Rp 27.000.000');
    }

    public function test_widget_keseluruhan_merangkum_seluruh_unit_kerja(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $this->seedPagu($this->unitB, 10_000_000);

        $pengajuanA = $this->seedPengajuan($this->unitA, 8_000_000, EnumStatusPengajuan::Diterima);
        $pengajuanB = $this->seedPengajuan($this->unitB, 7_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuanA, 6_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 6_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
        ]);
        $this->seedRealisasi($pengajuanB, 3_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 3_000_000,
            'dicairkan_at' => Carbon::create(2026, 5, 1),
        ]);

        // Tanpa unit kerja terpilih, widget merangkum seluruh unit yang boleh diakses.
        Livewire::test(BukuAnggaranOverview::class, [
            'unitKerjaId' => null,
            'tahunKerjaId' => $this->tahunKerja->id,
        ])
            ->assertOk()
            ->assertSee('TA 2026')
            // Dua realisasi kedua unit, 9jt dari total pagu 30jt.
            ->assertSee('100% sudah selesai (2 dari 2)')
            ->assertSee('Rp 9.000.000')
            ->assertSee('30% dari pagu Rp 30.000.000');

        $data = $this->dataGrafik(null, $this->tahunKerja->id);

        // Debit kedua unit tergambar pada bulan pencairannya masing-masing.
        $this->assertSame(6_000_000.0, $data['datasets'][1]['data'][2]);
        $this->assertSame(3_000_000.0, $data['datasets'][1]['data'][4]);
    }

    public function test_halaman_mengikuti_tahun_kerja_yang_dipilih(): void
    {
        $tahunLalu = $this->seedTahunKerjaLain();

        $this->seedPagu($this->unitA, 20_000_000);
        $this->seedPagu($this->unitA, 5_000_000, $tahunLalu);

        Livewire::test(BukuAnggaranPage::class)
            ->assertOk()
            // Bawaannya tahun kerja aktif.
            ->assertSet('tahunKerjaId', $this->tahunKerja->id)
            ->assertSee('Rp 20.000.000')
            ->set('data.tahunKerjaId', $tahunLalu->id)
            ->assertSet('tahunKerjaId', $tahunLalu->id)
            ->assertSee('TA 2025')
            ->assertSee('Rp 5.000.000')
            ->assertDontSee('Rp 20.000.000');
    }

    public function test_widget_menampilkan_tahun_kerja_dan_realisasi_yang_sudah_dilaksanakan(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 12_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuan, 8_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 8_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
        ]);
        $this->seedRealisasi($pengajuan, 2_000_000, EnumStatusRealisasi::MenungguLaporan, [
            'nominal_disetujui' => 2_000_000,
            'dicairkan_at' => Carbon::create(2026, 4, 1),
        ]);
        // Draf belum dilaksanakan, jadi tidak ikut terhitung.
        $this->seedRealisasi($pengajuan, 1_000_000, EnumStatusRealisasi::Draft);

        Livewire::test(BukuAnggaranOverview::class, [
            'unitKerjaId' => $this->unitA->id,
            'tahunKerjaId' => $this->tahunKerja->id,
        ])
            ->assertOk()
            ->assertSee('TA 2026')
            ->assertSee('01 Januari 2026 – 31 Desember 2026')
            // Dua realisasi dilaksanakan, satu di antaranya selesai.
            ->assertSee('50% sudah selesai (1 dari 2)')
            ->assertSee('Rp 10.000.000')
            ->assertSee('50% dari pagu Rp 20.000.000');
    }

    public function test_widget_tidak_menghitung_realisasi_tahun_kerja_lain(): void
    {
        $tahunLalu = $this->seedTahunKerjaLain();

        $pengajuan = $this->seedPengajuan($this->unitA, 12_000_000, EnumStatusPengajuan::Diterima);
        $this->seedRealisasi($pengajuan, 8_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 8_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
        ]);

        Livewire::test(BukuAnggaranOverview::class, [
            'unitKerjaId' => $this->unitA->id,
            'tahunKerjaId' => $tahunLalu->id,
        ])
            ->assertOk()
            ->assertSee('TA 2025')
            ->assertSee('Belum ada realisasi yang dilaksanakan')
            ->assertDontSee('Rp 8.000.000');
    }

    public function test_grafik_batang_memisahkan_kredit_debit_dan_pemasukan_per_bulan(): void
    {
        $this->seedPagu($this->unitA, 20_000_000);
        $pengajuan = $this->seedPengajuan($this->unitA, 10_000_000, EnumStatusPengajuan::Diterima);

        $this->seedRealisasi($pengajuan, 6_000_000, EnumStatusRealisasi::Selesai, [
            'nominal_disetujui' => 8_000_000,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
            'status_anggaran' => EnumStatusAnggaran::Sisa,
            'nominal_selisih_anggaran' => 2_000_000,
            'status_penyelesaian_anggaran' => EnumStatusPenyelesaianAnggaran::Dikembalikan,
            'penyelesaian_anggaran_at' => Carbon::create(2026, 4, 1),
        ]);

        Pemasukan::create([
            'unit_kerja_id' => $this->unitA->id,
            'sumber' => EnumSumberPemasukan::Realisasi,
            'rincian_kegiatan' => 'Seminar nasional',
            'tanggal_pelaksanaan' => Carbon::create(2026, 2, 20),
            'nominal_pendapatan' => 3_000_000,
            'status' => EnumStatusPemasukan::Valid,
        ]);

        $data = $this->dataGrafik($this->unitA->id, $this->tahunKerja->id);

        // Sumbu membentang sepanjang rentang waktu tahun kerja.
        $this->assertSame(
            ['Jan 2026', 'Feb 2026', 'Mar 2026', 'Apr 2026', 'Mei 2026', 'Jun 2026', 'Jul 2026', 'Agt 2026', 'Sep 2026', 'Okt 2026', 'Nov 2026', 'Des 2026'],
            $data['labels'],
        );

        [$kredit, $debit, $pemasukan] = $data['datasets'];

        // Pagu Januari tidak digambar karena ia saldo pembuka, bukan mutasi bulan itu.
        $this->assertSame(0.0, $kredit['data'][0]);
        $this->assertSame(2_000_000.0, $kredit['data'][3]);
        $this->assertSame(8_000_000.0, $debit['data'][2]);
        $this->assertSame(3_000_000.0, $pemasukan['data'][1]);
        $this->assertSame(2_000_000.0, array_sum($kredit['data']));
        $this->assertSame(8_000_000.0, array_sum($debit['data']));
    }

    public function test_grafik_batang_kosong_saat_tahun_kerja_belum_punya_mutasi(): void
    {
        $tahunLalu = $this->seedTahunKerjaLain();

        $this->seedPagu($this->unitA, 20_000_000);

        // Hanya ada pagu tahun aktif; tahun lalu belum bermutasi sama sekali.
        $this->assertSame([], $this->dataGrafik($this->unitA->id, $tahunLalu->id));

        Livewire::test(MutasiAnggaranChart::class, [
            'unitKerjaId' => $this->unitA->id,
            'tahunKerjaId' => $tahunLalu->id,
        ])
            ->assertOk()
            ->assertSee('Mutasi Anggaran per Bulan');
    }

    /**
     * Data grafik yang biasanya dipakai Chart.js, diambil lewat refleksi karena
     * `getData()` bersifat protected.
     *
     * @return array<string, mixed>
     */
    private function dataGrafik(?int $unitKerjaId, ?int $tahunKerjaId): array
    {
        $widget = new MutasiAnggaranChart;
        $widget->unitKerjaId = $unitKerjaId;
        $widget->tahunKerjaId = $tahunKerjaId;

        /** @var array<string, mixed> $data */
        $data = (new \ReflectionMethod($widget, 'getData'))->invoke($widget);

        return $data;
    }

    private function seedPagu(UnitKerja $unit, float $amount, ?TahunKerja $tahunKerja = null): PaguAnggaran
    {
        return PaguAnggaran::create([
            'tahun_kerja_id' => ($tahunKerja ?? $this->tahunKerja)->id,
            'unit_kerja_id' => $unit->id,
            'amount' => $amount,
        ]);
    }

    /**
     * Tahun kerja lain yang tidak aktif, untuk menguji penyaring tahun kerja.
     */
    private function seedTahunKerjaLain(): TahunKerja
    {
        return TahunKerja::create([
            'periode_id' => $this->tahunKerja->periode_id,
            'name' => 'TA 2025',
            'start_datetime' => Carbon::create(2025, 1, 1),
            'end_datetime' => Carbon::create(2025, 12, 31),
            'status' => EnumStatusTahunKerja::Selesai,
        ]);
    }

    private function seedPengajuan(UnitKerja $unit, float $alokasi, EnumStatusPengajuan $status, ?TahunKerja $tahunKerja = null): PengajuanProgramKerja
    {
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => ($tahunKerja ?? $this->tahunKerja)->id,
            'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::firstOrCreate(['code' => 'B1'], ['name' => 'Akademik'])->id,
            'kategori_id' => Kategori::firstOrCreate(['code' => 'K1'], ['name' => 'Pendidikan'])->id,
            'program_id' => Program::firstOrCreate(['name' => 'Tridharma'])->id,
            'name' => 'Workshop '.$unit->name.' '.fake()->unique()->word(),
            'is_active' => true,
        ]);

        return PengajuanProgramKerja::create([
            'penawaran_program_kerja_id' => $penawaran->id,
            'unit_kerja_id' => $unit->id,
            'alokasi_anggaran' => $alokasi,
            'status' => $status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function seedRealisasi(
        PengajuanProgramKerja $pengajuan,
        float $digunakan,
        EnumStatusRealisasi $status,
        array $atribut = [],
    ): RealisasiProgramKerja {
        return RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'name' => 'Pelaksanaan',
            'anggaran_digunakan' => $digunakan,
            'status' => $status,
            ...$atribut,
        ]);
    }
}
