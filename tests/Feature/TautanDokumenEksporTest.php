<?php

namespace Tests\Feature;

use App\Enums\EnumJenisDokumenRealisasi;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Enums\EnumStatusTahunKerja;
use App\Exports\MonitoringRealisasisExport;
use App\Filament\Pages\PratinjauDokumenRealisasi;
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
use App\Services\Excel\SpreadsheetExporter;
use App\Services\KodeDokumenRealisasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use ZipArchive;

/**
 * Tautan dokumen pada ekspor Monitoring Realisasi: sel "Lihat Proposal"/"Lihat
 * Laporan" yang berisi kode, penukaran kode itu menjadi halaman pratinjau, dan
 * pratinjau berkas privat yang URL-nya dibuat baru setiap kali dibuka.
 */
class TautanDokumenEksporTest extends TestCase
{
    use RefreshDatabase;

    private TahunKerja $tahunKerja;

    private UnitKerja $unitA;

    private UnitKerja $unitB;

    protected function setUp(): void
    {
        parent::setUp();

        $periode = Periode::create([
            'name' => 'P1',
            'start_datetime' => Carbon::create(2026, 1, 1),
            'end_datetime' => Carbon::create(2026, 12, 31),
        ]);

        $this->tahunKerja = TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA 2026',
            'start_datetime' => Carbon::create(2026, 1, 1),
            'end_datetime' => Carbon::create(2026, 12, 31),
            'status' => EnumStatusTahunKerja::Berjalan,
        ]);

        $this->unitA = UnitKerja::create(['name' => 'Unit A']);
        $this->unitB = UnitKerja::create(['name' => 'Unit B']);

        $this->actingAs($this->penggunaUnit($this->unitA));
    }

    public function test_ekspor_menulis_sel_tautan_hanya_untuk_realisasi_yang_punya_dokumen(): void
    {
        $berdokumen = $this->seedRealisasi($this->unitA, [
            'name' => 'Kegiatan Berdokumen',
            'proposal_path' => ['proposal-realisasi/proposal-satu.pdf'],
            'laporan_path' => ['laporan-realisasi/laporan-satu.pdf'],
        ]);

        $this->seedRealisasi($this->unitA, ['name' => 'Kegiatan Tanpa Dokumen']);

        $xml = $this->lembarKerja(new MonitoringRealisasisExport([$this->unitA->id], $this->tahunKerja->id));

        $this->assertStringContainsString('HYPERLINK', $xml);
        $this->assertStringContainsString('Lihat Proposal', $xml);
        $this->assertStringContainsString('Lihat Laporan', $xml);

        // Kodenya, bukan alamat berkasnya, yang tertanam di dalam berkas ekspor.
        $this->assertStringContainsString(
            KodeDokumenRealisasi::untuk($berdokumen, EnumJenisDokumenRealisasi::Proposal),
            $xml,
        );
        $this->assertStringNotContainsString('proposal-satu.pdf', $xml);

        // Satu realisasi berdokumen: dua sel tautan, bukan empat.
        $this->assertSame(2, substr_count($xml, 'HYPERLINK'));
    }

    public function test_kode_ditukar_menjadi_halaman_pratinjau(): void
    {
        $realisasi = $this->seedRealisasi($this->unitA, [
            'proposal_path' => ['proposal-realisasi/proposal-satu.pdf'],
        ]);

        $this->get(KodeDokumenRealisasi::tautan($realisasi, EnumJenisDokumenRealisasi::Proposal))
            ->assertRedirect(PratinjauDokumenRealisasi::getUrl([
                'record' => $realisasi->uuid,
                'jenis' => 'proposal',
            ]));
    }

    public function test_tautan_menuntut_pengguna_masuk_lebih_dahulu(): void
    {
        $realisasi = $this->seedRealisasi($this->unitA, [
            'proposal_path' => ['proposal-realisasi/proposal-satu.pdf'],
        ]);

        $tautan = KodeDokumenRealisasi::tautan($realisasi, EnumJenisDokumenRealisasi::Proposal);

        auth()->logout();

        $this->get($tautan)->assertRedirect();
        $this->assertGuest();

        // Alamat yang dituju disimpan, sehingga sesudah masuk pengguna sampai ke dokumennya.
        $this->assertSame($tautan, session()->get('url.intended'));
    }

    public function test_kode_palsu_ditolak(): void
    {
        $realisasi = $this->seedRealisasi($this->unitA, [
            'proposal_path' => ['proposal-realisasi/proposal-satu.pdf'],
        ]);

        $kode = KodeDokumenRealisasi::untuk($realisasi, EnumJenisDokumenRealisasi::Proposal);

        $this->assertNotNull(KodeDokumenRealisasi::bongkar($kode));
        $this->assertNull(KodeDokumenRealisasi::bongkar(substr($kode, 0, -1)));
        $this->assertNull(KodeDokumenRealisasi::bongkar('sembarang'));

        $this->get(route('filament.app.'.KodeDokumenRealisasi::NAMA_RUTE, ['kode' => 'sembarang.abc']))
            ->assertNotFound();
    }

    public function test_pratinjau_menampilkan_seluruh_berkas_dan_dapat_berpindah(): void
    {
        $realisasi = $this->seedRealisasi($this->unitA, [
            'proposal_path' => [
                'proposal-realisasi/proposal-awal.pdf',
                'proposal-realisasi/proposal-revisi.pdf',
            ],
            'proposal_original_names' => [
                'proposal-realisasi/proposal-awal.pdf' => 'Proposal Awal.pdf',
                'proposal-realisasi/proposal-revisi.pdf' => 'Proposal Revisi.pdf',
            ],
            'laporan_path' => ['laporan-realisasi/laporan-akhir.pdf'],
            'laporan_original_names' => ['laporan-realisasi/laporan-akhir.pdf' => 'Laporan Akhir.pdf'],
        ]);

        $halaman = Livewire::test(PratinjauDokumenRealisasi::class, [
            'record' => $realisasi->uuid,
            'jenis' => 'proposal',
        ])
            ->assertOk()
            ->assertSee('Proposal Awal.pdf')
            ->assertSee('Proposal Revisi.pdf');

        // Berkas terbaru dipilih lebih dulu; berkas lain bisa diklik.
        $this->assertContains($halaman->get('berkas'), [
            'proposal-realisasi/proposal-awal.pdf',
            'proposal-realisasi/proposal-revisi.pdf',
        ]);

        $halaman->call('pilihBerkas', 'proposal-realisasi/proposal-awal.pdf')
            ->assertSet('berkas', 'proposal-realisasi/proposal-awal.pdf');

        // Path milik realisasi lain tidak pernah ditandatangani.
        $halaman->call('pilihBerkas', '../../.env')
            ->assertSet('berkas', 'proposal-realisasi/proposal-awal.pdf');

        $halaman->call('pilihJenis', 'laporan')
            ->assertSet('jenis', 'laporan')
            ->assertSet('berkas', 'laporan-realisasi/laporan-akhir.pdf')
            ->assertSee('Laporan Akhir.pdf');
    }

    public function test_pratinjau_membuat_url_sementara_untuk_berkas_privat(): void
    {
        $realisasi = $this->seedRealisasi($this->unitA, [
            'proposal_path' => ['proposal-realisasi/proposal-satu.pdf'],
        ]);

        $halaman = new PratinjauDokumenRealisasi;
        $halaman->mount($realisasi->uuid, 'proposal');

        $pratinjau = $halaman->pratinjau();

        $this->assertNotNull($pratinjau);
        $this->assertSame('pdf', $pratinjau['extension']);
        $this->assertNotNull($pratinjau['url']);
        // URL sementara selalu bertanda tangan dan berbatas waktu.
        $this->assertStringContainsString('signature=', (string) $pratinjau['url']);
        $this->assertStringContainsString('expires=', (string) $pratinjau['url']);
    }

    public function test_pratinjau_tertutup_bagi_unit_di_luar_scope(): void
    {
        $realisasi = $this->seedRealisasi($this->unitB, [
            'proposal_path' => ['proposal-realisasi/proposal-unit-b.pdf'],
        ]);

        $this->get(PratinjauDokumenRealisasi::getUrl([
            'record' => $realisasi->uuid,
            'jenis' => 'proposal',
        ]))->assertForbidden();
    }

    public function test_pratinjau_menumpang_hak_akses_monitoring_realisasi(): void
    {
        Permission::findOrCreate('view_page_monitoring_realisasi', 'web');

        $realisasi = $this->seedRealisasi($this->unitA, [
            'proposal_path' => ['proposal-realisasi/proposal-satu.pdf'],
        ]);

        $this->actingAs(User::factory()->create(['unit_kerja_id' => $this->unitA->id]));

        $this->assertFalse(PratinjauDokumenRealisasi::canAccess());
        $this->assertSame([], PratinjauDokumenRealisasi::getPermissionDefinitions());

        $this->get(KodeDokumenRealisasi::tautan($realisasi, EnumJenisDokumenRealisasi::Proposal))
            ->assertForbidden();
    }

    /**
     * Isi XML lembar kerja pertama berkas .xlsx — di situlah rumus HYPERLINK terlihat
     * apa adanya, tanpa ditafsirkan pembaca spreadsheet.
     */
    private function lembarKerja(MonitoringRealisasisExport $export): string
    {
        $path = tempnam(sys_get_temp_dir(), 'uji_tautan_').'.xlsx';

        app(SpreadsheetExporter::class)->write($export, $path);

        $zip = new ZipArchive;
        $zip->open($path);
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        @unlink($path);

        return $xml;
    }

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function seedRealisasi(UnitKerja $unit, array $atribut = []): RealisasiProgramKerja
    {
        $penawaran = PenawaranProgramKerja::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $unit->id,
            'bidang_id' => Bidang::firstOrCreate(['code' => 'B1'], ['name' => 'Akademik'])->id,
            'kategori_id' => Kategori::firstOrCreate(['code' => 'K1'], ['name' => 'Pendidikan'])->id,
            'program_id' => Program::firstOrCreate(['name' => 'Tridharma'])->id,
            'name' => 'Program '.fake()->unique()->words(3, true),
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
            'name' => 'Pelaksanaan',
            'anggaran_digunakan' => 5_000_000,
            'nominal_disetujui' => 5_000_000,
            'status' => EnumStatusRealisasi::MenungguLaporan,
            'dicairkan_at' => Carbon::create(2026, 3, 1),
            ...$atribut,
        ]);
    }

    private function penggunaUnit(UnitKerja $unitKerja): User
    {
        $user = User::factory()->create(['unit_kerja_id' => $unitKerja->id]);
        $user->givePermissionTo(Permission::findOrCreate('view_page_monitoring_realisasi', 'web'));

        return $user;
    }
}
