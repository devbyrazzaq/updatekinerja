<?php

namespace Tests\Feature;

use App\Enums\EnumMetodePembayaran;
use App\Enums\EnumStatusPencairan;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Exports\JadwalPencairanExport;
use App\Filament\Resources\JadwalPencairans\JadwalPencairanResource;
use App\Filament\Resources\JadwalPencairans\Pages\CreateJadwalPencairan;
use App\Filament\Resources\JadwalPencairans\Pages\ListJadwalPencairans;
use App\Filament\Resources\JadwalPencairans\Pages\ViewJadwalPencairan;
use App\Filament\Resources\JadwalPencairans\RelationManagers\RealisasiProgramKerjasRelationManager;
use App\Filament\Resources\JadwalPencairans\Widgets\JadwalPencairanOverview;
use App\Filament\Resources\VerifikasiBiroKeuangans\Pages\ListVerifikasiBiroKeuangans;
use App\Models\Bank;
use App\Models\Bidang;
use App\Models\JadwalPencairan;
use App\Models\Kategori;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\RealisasiDokumen;
use App\Models\RealisasiProgramKerja;
use App\Models\RekeningBank;
use App\Models\Setting;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use App\Reports\LaporanPencairanReport;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\TestCase;

class JadwalPencairanTest extends TestCase
{
    use RefreshDatabase;

    private ?TahunKerja $tahunKerja = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /**
     * Tahun kerja aktif yang menjadi konteks seluruh data pada test ini.
     */
    private function tahunKerja(): TahunKerja
    {
        if ($this->tahunKerja === null) {
            $periode = Periode::create(['name' => 'P1', 'start_datetime' => now(), 'end_datetime' => now()->addYear()]);
            $this->tahunKerja = TahunKerja::create(['periode_id' => $periode->id, 'name' => 'TA 2026', 'start_datetime' => now(), 'end_datetime' => now()->addYear(), 'status' => EnumStatusTahunKerja::Berjalan]);
        }

        return $this->tahunKerja;
    }

    private function realisasiAtKeuangan(string $nama = 'Kegiatan'): RealisasiProgramKerja
    {
        $unit = UnitKerja::create(['name' => 'Unit '.$nama]);
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $this->tahunKerja()->id, 'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::create(['code' => 'B'.$nama, 'name' => 'B'])->id,
            'kategori_id' => Kategori::create(['code' => 'K'.$nama, 'name' => 'K'])->id,
            'program_id' => Program::create(['name' => 'P'])->id,
            'name' => 'Prokerja', 'is_active' => true,
        ]);
        $pengajuan = PengajuanProgramKerja::create(['penawaran_program_kerja_id' => $penawaran->id, 'unit_kerja_id' => $unit->id, 'alokasi_anggaran' => 20000000, 'status' => EnumStatusPengajuan::Diterima]);

        return RealisasiProgramKerja::create([
            'pengajuan_program_kerja_id' => $pengajuan->id,
            'name' => $nama,
            'anggaran_digunakan' => 15000000,
            'nominal_disetujui' => 15000000,
            'status' => EnumStatusRealisasi::VerifikasiKeuangan,
        ]);
    }

    private function jadwal(string $name = 'Pencairan Awal Bulan Januari'): JadwalPencairan
    {
        return JadwalPencairan::create([
            'tahun_kerja_id' => $this->tahunKerja()->id,
            'name' => $name,
            'tanggal_pencairan' => now()->addWeek()->toDateString(),
            'status' => EnumStatusPencairan::Dijadwalkan,
        ]);
    }

    /**
     * Rekening bank milik satu unit kerja, atau rekening umum bila unit kerja null.
     */
    private function rekeningBank(?int $unitKerjaId, bool $utama = false): RekeningBank
    {
        return RekeningBank::create([
            'bank_id' => Bank::factory()->create()->id,
            'unit_kerja_id' => $unitKerjaId,
            'nomor_rekening' => fake()->unique()->numerify('##########'),
            'atas_nama' => 'Pemilik Rekening',
            'is_utama' => $utama,
            'is_active' => true,
        ]);
    }

    public function test_list_pages_render(): void
    {
        Livewire::test(ListVerifikasiBiroKeuangans::class)->assertOk();
        Livewire::test(ListJadwalPencairans::class)->assertOk();
    }

    /**
     * Ringkasan menghitung banyaknya jadwal, nominal yang sudah diserahkan, dan
     * jadwal yang selesai — yaitu jadwal yang seluruh realisasinya sudah dicairkan.
     */
    public function test_widget_ringkasan_menghitung_jadwal_dan_nominal_dicairkan(): void
    {
        $selesai = $this->jadwal('Pencairan Januari');
        $berjalan = $this->jadwal('Pencairan Februari');

        $dicairkan = $this->realisasiAtKeuangan('Kegiatan A');
        $dicairkan->jadwalkanPencairan($selesai, null, null, EnumMetodePembayaran::Tunai);
        $selesai->cairkan();

        $belumCair = $this->realisasiAtKeuangan('Kegiatan B');
        $belumCair->update(['nominal_disetujui' => 5000000]);
        $belumCair->jadwalkanPencairan($berjalan, null, null, EnumMetodePembayaran::Tunai);

        Livewire::test(JadwalPencairanOverview::class)
            ->assertOk()
            ->assertSee('Jumlah Jadwal')
            // 2 jadwal, 1 di antaranya belum dicairkan seluruhnya
            ->assertSee('1 jadwal belum dicairkan seluruhnya')
            // Rp 15.000.000 dari Rp 20.000.000 yang dijadwalkan
            ->assertSee('Rp 15.000.000')
            ->assertSee('Jadwal Selesai');

        $this->assertSame(EnumStatusPencairan::Dicairkan, $selesai->refresh()->status);
        $this->assertSame(EnumStatusPencairan::Dijadwalkan, $berjalan->refresh()->status);
    }

    /**
     * Halaman detail menampilkan total yang akan dicairkan beserta daftar realisasinya.
     */
    public function test_view_page_menampilkan_total_dan_realisasi(): void
    {
        $jadwal = $this->jadwal();
        $realisasi = $this->realisasiAtKeuangan();
        $realisasi->jadwalkanPencairan($jadwal);

        Livewire::test(ViewJadwalPencairan::class, ['record' => $jadwal->getRouteKey()])
            ->assertOk()
            ->assertSee('Pencairan Awal Bulan Januari')
            ->assertSee('Total Akan Dicairkan');

        Livewire::test(RealisasiProgramKerjasRelationManager::class, [
            'ownerRecord' => $jadwal,
            'pageClass' => ViewJadwalPencairan::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords([$realisasi]);
    }

    /**
     * Jadwal dialamatkan lewat uuid: id yang berurutan tidak lagi menjadi kunci rute,
     * dan uuid-nya terbentuk sendiri saat jadwal dibuat.
     */
    public function test_jadwal_dialamatkan_lewat_uuid(): void
    {
        $jadwal = $this->jadwal();

        $this->assertSame('uuid', $jadwal->getRouteKeyName());
        $this->assertNotEmpty($jadwal->uuid);
        $this->assertSame($jadwal->uuid, $jadwal->getRouteKey());
        $this->assertNotSame((string) $jadwal->id, $jadwal->getRouteKey());

        $this->assertTrue($jadwal->is(JadwalPencairanResource::resolveRecordRouteBinding($jadwal->uuid)));

        // Tautan lama yang memakai id tidak lagi menemukan jadwalnya.
        $this->expectException(ModelNotFoundException::class);
        JadwalPencairanResource::resolveRecordRouteBinding((string) $jadwal->id);
    }

    /**
     * Ekspor .xlsx satu jadwal memuat rincian tiap realisasi beserta rekening
     * tujuannya, dan ringkasannya menyebut total yang dicairkan.
     */
    public function test_ekspor_jadwal_memuat_rincian_realisasi_dan_rekening(): void
    {
        $jadwal = $this->jadwal();
        $realisasi = $this->realisasiAtKeuangan('Pelatihan Dosen');
        $rekening = $this->rekeningBank($realisasi->pengajuanProgramKerja->unit_kerja_id, utama: true);
        $realisasi->jadwalkanPencairan($jadwal, null, null, EnumMetodePembayaran::Transfer, $rekening->id);

        $export = new JadwalPencairanExport($jadwal->refresh());
        $rows = $export->rows();

        $this->assertCount(1, $rows);
        $this->assertSame('Pelatihan Dosen', $rows[0][1]);
        $this->assertSame(15000000.0, $rows[0][2]);
        $this->assertSame('Transfer ke Rekening', $rows[0][3]);
        $this->assertSame($rekening->nomor_rekening, $rows[0][5]);
        $this->assertSame('Rp 15.000.000', $export->summary()['Total Pencairan']);
    }

    /**
     * Laporan PDF pencairan mencetak tabel rincian, tanggal yang dipilih saat
     * mengunduh, serta blok tanda tangan sesuai Pengaturan Sistem.
     */
    public function test_laporan_pencairan_mencetak_rincian_dan_blok_tanda_tangan(): void
    {
        Setting::set(Setting::PENANDATANGAN_JABATAN, 'Kepala Biro Keuangan');
        Setting::set(Setting::PENANDATANGAN_NAMA, 'Dr. Hj. Siti Aminah, S.E., M.M.');
        Setting::set(Setting::PENANDATANGAN_NOMOR, 'NIK 198701012015041002');
        Setting::set(Setting::PENANDATANGAN_KOTA, 'Lamongan');
        Setting::forgetCache();

        $jadwal = $this->jadwal();
        $realisasi = $this->realisasiAtKeuangan('Pelatihan Dosen');
        $rekening = $this->rekeningBank($realisasi->pengajuanProgramKerja->unit_kerja_id, utama: true);
        $realisasi->jadwalkanPencairan($jadwal, null, null, EnumMetodePembayaran::Transfer, $rekening->id);

        $report = new LaporanPencairanReport($jadwal->refresh(), Carbon::parse('2026-08-17'));
        $html = View::make($report->view(), $report->data())->render();

        $this->assertStringContainsString('Laporan Pencairan Anggaran', $html);
        $this->assertStringContainsString('Pelatihan Dosen', $html);
        $this->assertStringContainsString($rekening->nomor_rekening, $html);
        $this->assertStringContainsString('Rp 15.000.000', $html);
        // Blok tanda tangan: kota + tanggal pilihan, jabatan, nama pimpinan lengkap
        // dengan gelarnya, lalu nomor karyawannya.
        $this->assertStringContainsString('Lamongan, 17 Agustus 2026', $html);
        $this->assertStringContainsString('Kepala Biro Keuangan', $html);
        $this->assertStringContainsString('Dr. Hj. Siti Aminah, S.E., M.M.', $html);
        $this->assertStringContainsString('NIK 198701012015041002', $html);
    }

    /**
     * Tombol laporan pada halaman detail meneruskan tanggal dari modal ke berkas
     * yang diunduh.
     */
    public function test_aksi_laporan_pdf_mengunduh_berkas_dengan_tanggal_pilihan(): void
    {
        $jadwal = $this->jadwal();
        $realisasi = $this->realisasiAtKeuangan();
        $realisasi->jadwalkanPencairan($jadwal, null, null, EnumMetodePembayaran::Tunai);

        $report = new LaporanPencairanReport($jadwal, Carbon::parse('2026-08-17'));

        $this->assertSame(
            'laporan-pencairan-pencairan-awal-bulan-januari-'.$jadwal->tanggal_pencairan->format('Y-m-d'),
            $report->filename(),
        );
        $this->assertSame('17 Agustus 2026', $report->data()['tanggal']->locale('id')->translatedFormat('d F Y'));

        Livewire::test(ViewJadwalPencairan::class, ['record' => $jadwal->getRouteKey()])
            ->assertActionExists('export')
            ->assertActionExists('report');
    }

    public function test_jadwal_pencairan_dibuat_dengan_nama_dan_tanggal(): void
    {
        Livewire::test(CreateJadwalPencairan::class)
            ->fillForm([
                'name' => 'Pencairan Awal Bulan Januari',
                'tanggal_pencairan' => '2026-01-05',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(JadwalPencairan::class, [
            'name' => 'Pencairan Awal Bulan Januari',
            'status' => EnumStatusPencairan::Dijadwalkan->value,
        ]);
    }

    public function test_nama_dan_tanggal_wajib_diisi(): void
    {
        Livewire::test(CreateJadwalPencairan::class)
            ->fillForm(['name' => null, 'tanggal_pencairan' => null])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'tanggal_pencairan' => 'required']);
    }

    public function test_proses_pencairan_menjadwalkan_realisasi_ke_jadwal(): void
    {
        $realisasi = $this->realisasiAtKeuangan();
        $jadwal = $this->jadwal();
        $rekening = $this->rekeningBank($realisasi->unitKerjaId(), utama: true);

        Livewire::test(ListVerifikasiBiroKeuangans::class)
            ->callAction(TestAction::make('prosesPencairan')->table($realisasi), [
                'jadwal_pencairan_id' => $jadwal->id,
            ])
            ->assertHasNoActionErrors();

        $realisasi->refresh();
        $this->assertSame($jadwal->id, $realisasi->jadwal_pencairan_id);
        $this->assertSame(EnumStatusRealisasi::Dijadwalkan, $realisasi->status);
        $this->assertSame(EnumStatusPencairan::Dijadwalkan, $realisasi->status_pencairan);
        $this->assertSame($rekening->id, $realisasi->rekening_bank_id);
    }

    public function test_keuangan_wajib_memilih_jadwal(): void
    {
        $realisasi = $this->realisasiAtKeuangan();

        Livewire::test(ListVerifikasiBiroKeuangans::class)
            ->callAction(TestAction::make('prosesPencairan')->table($realisasi), [
                // jadwal_pencairan_id sengaja dikosongkan
            ])
            ->assertHasActionErrors(['jadwal_pencairan_id']);
    }

    /**
     * Total pencairan satu jadwal = jumlah nominal seluruh realisasi anggotanya.
     */
    public function test_total_pencairan_dihitung_dari_realisasi_terjadwal(): void
    {
        $jadwal = $this->jadwal();
        $pertama = $this->realisasiAtKeuangan('Kegiatan A');
        $kedua = $this->realisasiAtKeuangan('Kegiatan B');
        $kedua->update(['nominal_disetujui' => 5000000]);

        $pertama->jadwalkanPencairan($jadwal);
        $kedua->jadwalkanPencairan($jadwal);

        $jadwal->refresh();
        $this->assertSame(2, $jadwal->jumlahRealisasi());
        $this->assertSame(20000000.0, $jadwal->totalNominal());

        Livewire::test(ListJadwalPencairans::class)
            ->assertCanSeeTableRecords([$jadwal])
            ->assertSee('2 realisasi');
    }

    /**
     * Pencairan satu jadwal memakai rencana pembayaran tiap realisasi yang dipilih
     * saat penjadwalan, sehingga satu jadwal boleh memuat campuran transfer dan tunai.
     */
    public function test_cairkan_jadwal_memakai_rencana_pembayaran_masing_masing(): void
    {
        $jadwal = $this->jadwal();
        $transfer = $this->realisasiAtKeuangan('Kegiatan A');
        $tunai = $this->realisasiAtKeuangan('Kegiatan B');
        $rekening = $this->rekeningBank($transfer->unitKerjaId(), utama: true);

        $transfer->jadwalkanPencairan($jadwal, null, null, EnumMetodePembayaran::Transfer, $rekening->id);
        $tunai->jadwalkanPencairan($jadwal, null, null, EnumMetodePembayaran::Tunai);

        Livewire::test(ListJadwalPencairans::class)
            ->callAction(TestAction::make('tandaiDicairkan')->table($jadwal))
            ->assertHasNoActionErrors();

        $jadwal->refresh();
        $transfer->refresh();
        $tunai->refresh();

        $this->assertSame(EnumStatusPencairan::Dicairkan, $jadwal->status);
        $this->assertNotNull($jadwal->dicairkan_at);
        $this->assertSame(EnumStatusRealisasi::MenungguLaporan, $transfer->status);
        $this->assertSame(EnumMetodePembayaran::Transfer, $transfer->metode_pembayaran);
        $this->assertSame($rekening->id, $transfer->rekening_bank_id);
        $this->assertSame(EnumMetodePembayaran::Tunai, $tunai->metode_pembayaran);
        $this->assertNull($tunai->rekening_bank_id);
    }

    /**
     * Jadwal tidak dicairkan sekaligus selama masih ada realisasi transfer yang
     * rekening tujuannya belum ditentukan.
     */
    public function test_jadwal_tidak_dicairkan_bila_rekening_tujuan_belum_ada(): void
    {
        $jadwal = $this->jadwal();
        $realisasi = $this->realisasiAtKeuangan();
        $realisasi->jadwalkanPencairan($jadwal, null, null, EnumMetodePembayaran::Transfer, null);

        Livewire::test(ListJadwalPencairans::class)
            ->callAction(TestAction::make('tandaiDicairkan')->table($jadwal))
            ->assertHasNoActionErrors();

        $this->assertSame(EnumStatusRealisasi::Dijadwalkan, $realisasi->refresh()->status);
        $this->assertSame(EnumStatusPencairan::Dijadwalkan, $jadwal->refresh()->status);
    }

    /**
     * Rekening utama unit kerja pengaju terisi otomatis pada modal penjadwalan,
     * namun tetap dapat diganti.
     */
    public function test_rekening_utama_unit_kerja_terisi_otomatis_saat_penjadwalan(): void
    {
        $realisasi = $this->realisasiAtKeuangan();
        $jadwal = $this->jadwal();
        $this->rekeningBank($realisasi->unitKerjaId());
        $utama = $this->rekeningBank($realisasi->unitKerjaId(), utama: true);

        Livewire::test(ListVerifikasiBiroKeuangans::class)
            ->mountAction(TestAction::make('prosesPencairan')->table($realisasi))
            ->assertActionDataSet(['rekening_bank_id' => $utama->id])
            ->setActionData(['jadwal_pencairan_id' => $jadwal->id])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $realisasi->refresh();
        $this->assertSame(EnumMetodePembayaran::Transfer, $realisasi->metode_pembayaran);
        $this->assertSame($utama->id, $realisasi->rekening_bank_id);
    }

    /**
     * Dari modal "tambah rekening bank" pada penjadwalan, bank yang belum terdaftar
     * dapat langsung didaftarkan lewat modal bersarang "tambah bank", lalu rekening
     * baru itu terpilih sebagai rekening tujuan.
     */
    public function test_bank_dan_rekening_baru_dapat_ditambahkan_saat_penjadwalan(): void
    {
        $realisasi = $this->realisasiAtKeuangan();
        $jadwal = $this->jadwal();

        Livewire::test(ListVerifikasiBiroKeuangans::class)
            ->callAction([
                TestAction::make('prosesPencairan')->table($realisasi),
                TestAction::make('createOption')->schemaComponent('rekening_bank_id'),
                TestAction::make('createOption')->schemaComponent('bank_id'),
            ], ['name' => 'Bank Nagari', 'code' => '118'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas(Bank::class, ['name' => 'Bank Nagari', 'is_active' => true]);
        $bank = Bank::query()->where('name', 'Bank Nagari')->sole();

        Livewire::test(ListVerifikasiBiroKeuangans::class)
            ->callAction([
                TestAction::make('prosesPencairan')->table($realisasi),
                TestAction::make('createOption')->schemaComponent('rekening_bank_id'),
            ], [
                'bank_id' => $bank->id,
                'unit_kerja_id' => $realisasi->unitKerjaId(),
                'nomor_rekening' => '7770008888',
                'atas_nama' => 'Unit Kegiatan',
            ])
            ->assertHasNoActionErrors();

        $rekening = RekeningBank::query()->where('nomor_rekening', '7770008888')->sole();
        $this->assertSame($bank->id, $rekening->bank_id);
        $this->assertSame($realisasi->unitKerjaId(), $rekening->unit_kerja_id);

        Livewire::test(ListVerifikasiBiroKeuangans::class)
            ->callAction(TestAction::make('prosesPencairan')->table($realisasi), [
                'jadwal_pencairan_id' => $jadwal->id,
                'rekening_bank_id' => $rekening->id,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame($rekening->id, $realisasi->refresh()->rekening_bank_id);
    }

    /**
     * Hanya rekening milik unit kerja pengaju dan rekening umum yang ditawarkan,
     * sehingga anggaran tidak dikirim ke rekening unit lain.
     */
    public function test_rekening_unit_lain_tidak_ditawarkan(): void
    {
        $realisasi = $this->realisasiAtKeuangan('Kegiatan A');
        $lain = $this->realisasiAtKeuangan('Kegiatan B');
        $milikUnitIni = $this->rekeningBank($realisasi->unitKerjaId(), utama: true);
        $milikUnitLain = $this->rekeningBank($lain->unitKerjaId(), utama: true);
        $umum = $this->rekeningBank(null);

        $opsi = RekeningBank::opsiUntukUnitKerja($realisasi->unitKerjaId());

        $this->assertArrayHasKey($milikUnitIni->id, $opsi);
        $this->assertArrayHasKey($umum->id, $opsi);
        $this->assertArrayNotHasKey($milikUnitLain->id, $opsi);
        $this->assertSame($milikUnitIni->id, RekeningBank::bawaanUntukUnitKerja($realisasi->unitKerjaId())?->id);
    }

    public function test_hanya_satu_rekening_utama_per_unit_kerja(): void
    {
        $realisasi = $this->realisasiAtKeuangan();
        $pertama = $this->rekeningBank($realisasi->unitKerjaId(), utama: true);
        $kedua = $this->rekeningBank($realisasi->unitKerjaId(), utama: true);

        $this->assertFalse($pertama->refresh()->is_utama);
        $this->assertTrue($kedua->refresh()->is_utama);
    }

    /**
     * Pembayaran tunai melepas tautan rekening walau sebelumnya sempat dipilih.
     */
    public function test_pembayaran_tunai_melepas_rekening_tujuan(): void
    {
        $jadwal = $this->jadwal();
        $realisasi = $this->realisasiAtKeuangan();
        $rekening = $this->rekeningBank($realisasi->unitKerjaId(), utama: true);
        $realisasi->jadwalkanPencairan($jadwal, null, null, EnumMetodePembayaran::Transfer, $rekening->id);

        Livewire::test(RealisasiProgramKerjasRelationManager::class, [
            'ownerRecord' => $jadwal,
            'pageClass' => ViewJadwalPencairan::class,
        ])
            ->callAction(TestAction::make('tandaiDicairkan')->table($realisasi), [
                'metode_pembayaran' => EnumMetodePembayaran::Tunai->value,
            ])
            ->assertHasNoActionErrors();

        $realisasi->refresh();
        $this->assertSame(EnumMetodePembayaran::Tunai, $realisasi->metode_pembayaran);
        $this->assertNull($realisasi->rekening_bank_id);
        $this->assertSame(EnumStatusRealisasi::MenungguLaporan, $realisasi->status);
    }

    /**
     * Penjadwalan transfer wajib menentukan rekening tujuan; pilihan tunai tidak.
     */
    public function test_transfer_wajib_menyertakan_rekening_tujuan(): void
    {
        $realisasi = $this->realisasiAtKeuangan();
        $jadwal = $this->jadwal();

        Livewire::test(ListVerifikasiBiroKeuangans::class)
            ->callAction(TestAction::make('prosesPencairan')->table($realisasi), [
                'jadwal_pencairan_id' => $jadwal->id,
                'metode_pembayaran' => EnumMetodePembayaran::Transfer->value,
                'rekening_bank_id' => null,
            ])
            ->assertHasActionErrors(['rekening_bank_id' => 'required']);

        Livewire::test(ListVerifikasiBiroKeuangans::class)
            ->callAction(TestAction::make('prosesPencairan')->table($realisasi), [
                'jadwal_pencairan_id' => $jadwal->id,
                'metode_pembayaran' => EnumMetodePembayaran::Tunai->value,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(EnumMetodePembayaran::Tunai, $realisasi->refresh()->metode_pembayaran);
    }

    /**
     * Jadwal baru dianggap cair bila seluruh anggotanya sudah menerima anggaran;
     * pencairan satu realisasi saja menyisakan jadwal pada status "Dijadwalkan".
     */
    public function test_status_jadwal_mengikuti_realisasi_anggotanya(): void
    {
        $jadwal = $this->jadwal();
        $pertama = $this->realisasiAtKeuangan('Kegiatan A');
        $kedua = $this->realisasiAtKeuangan('Kegiatan B');
        $pertama->jadwalkanPencairan($jadwal);
        $kedua->jadwalkanPencairan($jadwal);

        $pertama->tandaiAnggaranDicairkan(auth()->id(), EnumMetodePembayaran::Tunai);
        $this->assertSame(EnumStatusPencairan::Dijadwalkan, $jadwal->refresh()->status);

        $kedua->tandaiAnggaranDicairkan(auth()->id(), EnumMetodePembayaran::Tunai);
        $this->assertSame(EnumStatusPencairan::Dicairkan, $jadwal->refresh()->status);
    }

    public function test_realisasi_dijadwalkan_lewat_relation_manager(): void
    {
        $jadwal = $this->jadwal();
        $realisasi = $this->realisasiAtKeuangan();

        Livewire::test(RealisasiProgramKerjasRelationManager::class, [
            'ownerRecord' => $jadwal,
            'pageClass' => ViewJadwalPencairan::class,
        ])
            ->callAction('jadwalkanRealisasi', ['realisasi_ids' => [$realisasi->id]])
            ->assertHasNoActionErrors();

        $this->assertSame($jadwal->id, $realisasi->refresh()->jadwal_pencairan_id);
        $this->assertSame(EnumStatusRealisasi::Dijadwalkan, $realisasi->status);
    }

    public function test_realisasi_dapat_dicairkan_satu_per_satu_dari_relation_manager(): void
    {
        $jadwal = $this->jadwal();
        $realisasi = $this->realisasiAtKeuangan();
        $realisasi->jadwalkanPencairan($jadwal);

        Livewire::test(RealisasiProgramKerjasRelationManager::class, [
            'ownerRecord' => $jadwal,
            'pageClass' => ViewJadwalPencairan::class,
        ])
            ->callAction(TestAction::make('tandaiDicairkan')->table($realisasi), [
                'metode_pembayaran' => EnumMetodePembayaran::Tunai->value,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(EnumStatusRealisasi::MenungguLaporan, $realisasi->refresh()->status);
    }

    public function test_realisasi_dapat_dikeluarkan_dari_jadwal(): void
    {
        $jadwal = $this->jadwal();
        $realisasi = $this->realisasiAtKeuangan();
        $realisasi->jadwalkanPencairan($jadwal);

        Livewire::test(RealisasiProgramKerjasRelationManager::class, [
            'ownerRecord' => $jadwal,
            'pageClass' => ViewJadwalPencairan::class,
        ])
            ->callAction(TestAction::make('keluarkanDariJadwal')->table($realisasi))
            ->assertHasNoActionErrors();

        $realisasi->refresh();
        $this->assertNull($realisasi->jadwal_pencairan_id);
        $this->assertSame(EnumStatusRealisasi::VerifikasiKeuangan, $realisasi->status);
    }

    public function test_tandai_dicairkan_dari_verifikasi_biro_keuangan(): void
    {
        $realisasi = $this->realisasiAtKeuangan();
        $jadwal = $this->jadwal();
        $rekening = $this->rekeningBank($realisasi->unitKerjaId(), utama: true);

        Livewire::test(ListVerifikasiBiroKeuangans::class)
            ->callAction(TestAction::make('prosesPencairan')->table($realisasi), [
                'jadwal_pencairan_id' => $jadwal->id,
            ])
            ->assertHasNoActionErrors()
            ->callAction(TestAction::make('tandaiDicairkan')->table($realisasi))
            ->assertHasNoActionErrors();

        $realisasi->refresh();
        $this->assertSame(EnumStatusRealisasi::MenungguLaporan, $realisasi->status);
        $this->assertSame(EnumStatusPencairan::Dicairkan, $realisasi->status_pencairan);
        $this->assertSame(EnumMetodePembayaran::Transfer, $realisasi->metode_pembayaran);
        $this->assertSame($rekening->id, $realisasi->rekening_bank_id);
        $this->assertNotNull($realisasi->dicairkan_at);
        $this->assertSame(EnumStatusPencairan::Dicairkan, $jadwal->refresh()->status);
    }

    /**
     * Daftar dokumen proposal dirender Livewire component: nama berkas beserta
     * ukurannya, lalu tanggal unggah di baris berikutnya.
     */
    public function test_daftar_dokumen_proposal_tampil_di_modal_detail(): void
    {
        $realisasi = $this->realisasiAtKeuangan();

        RealisasiDokumen::factory()->proposal()->create([
            'realisasi_program_kerja_id' => $realisasi->id,
            'original_name' => 'Proposal Kegiatan.pdf',
            'size' => 1536,
            'uploaded_at' => now()->setDate(2026, 7, 20)->setTime(9, 30),
        ]);

        Livewire::test(ListVerifikasiBiroKeuangans::class)
            ->mountAction(TestAction::make('view')->table($realisasi))
            ->assertMountedActionModalSee('Dokumen Proposal')
            ->assertMountedActionModalSee('Proposal Kegiatan.pdf')
            ->assertMountedActionModalSee('Diunggah 20 Juli 2026, 09:30');

        Livewire::test('realisasi-program-kerja-documents', [
            'record' => $realisasi,
            'relationship' => 'proposals',
            'emptyLabel' => 'Belum ada proposal.',
        ])
            ->assertOk()
            ->assertSee('Proposal Kegiatan.pdf')
            ->assertSee('1.5 KB')
            ->assertSee('Diunggah 20 Juli 2026, 09:30');
    }

    public function test_daftar_dokumen_menampilkan_teks_kosong_bila_belum_ada_berkas(): void
    {
        $realisasi = $this->realisasiAtKeuangan();

        Livewire::test('realisasi-program-kerja-documents', [
            'record' => $realisasi,
            'relationship' => 'proposals',
            'emptyLabel' => 'Belum ada proposal.',
        ])
            ->assertOk()
            ->assertSee('Belum ada proposal.');
    }

    /**
     * Detail dibuka sebagai modal (resource tidak punya halaman View) dan aksi
     * pencairan tersedia di footer modal tersebut.
     */
    public function test_aksi_pencairan_tersedia_di_footer_modal_detail(): void
    {
        $realisasi = $this->realisasiAtKeuangan();
        $jadwal = $this->jadwal();

        Livewire::test(ListVerifikasiBiroKeuangans::class)
            ->callAction([
                TestAction::make('view')->table($realisasi),
                TestAction::make('prosesPencairan'),
            ], [
                'jadwal_pencairan_id' => $jadwal->id,
                'metode_pembayaran' => EnumMetodePembayaran::Tunai->value,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(EnumStatusRealisasi::Dijadwalkan, $realisasi->refresh()->status);

        Livewire::test(ListVerifikasiBiroKeuangans::class)
            ->callAction([
                TestAction::make('view')->table($realisasi),
                TestAction::make('tandaiDicairkan'),
            ], ['metode_pembayaran' => EnumMetodePembayaran::Tunai->value])
            ->assertHasNoActionErrors();

        $this->assertSame(EnumStatusRealisasi::MenungguLaporan, $realisasi->refresh()->status);
    }

    /**
     * Selama masih menunggu anggaran diberikan realisasi tetap di tab utama;
     * setelah dicairkan hanya muncul di tab "Sudah Diproses".
     */
    public function test_realisasi_pindah_tab_setelah_anggaran_dicairkan(): void
    {
        $realisasi = $this->realisasiAtKeuangan();
        $realisasi->jadwalkanPencairan($this->jadwal());

        Livewire::test(ListVerifikasiBiroKeuangans::class, ['activeTab' => 'perlu'])
            ->assertCanSeeTableRecords([$realisasi]);

        $realisasi->refresh()->tandaiAnggaranDicairkan(auth()->id());

        Livewire::test(ListVerifikasiBiroKeuangans::class, ['activeTab' => 'perlu'])
            ->assertCanNotSeeTableRecords([$realisasi]);

        Livewire::test(ListVerifikasiBiroKeuangans::class, ['activeTab' => 'direspon'])
            ->assertCanSeeTableRecords([$realisasi]);
    }
}
