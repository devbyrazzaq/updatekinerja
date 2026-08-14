<?php

namespace Tests\Feature;

use App\Enums\EnumStatusPemasukan;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Actions\TahapVerifikasiPemasukanAction;
use App\Filament\Resources\Pemasukans\Pages\ListPemasukans;
use App\Filament\Resources\Pemasukans\Pages\ViewPemasukan;
use App\Filament\Resources\Pemasukans\PemasukanResource;
use App\Filament\Resources\VerifikasiKeuanganPemasukans\Pages\ViewVerifikasiKeuanganPemasukan;
use App\Filament\Resources\VerifikasiKeuanganPemasukans\VerifikasiKeuanganPemasukanResource;
use App\Filament\Resources\VerifikasiRektorPemasukans\Pages\ListVerifikasiRektorPemasukans;
use App\Filament\Resources\VerifikasiRektorPemasukans\Pages\ViewVerifikasiRektorPemasukan;
use App\Filament\Resources\VerifikasiRektorPemasukans\VerifikasiRektorPemasukanResource;
use App\Filament\Resources\VerifikasiWakilPemasukans\Pages\ViewVerifikasiWakilPemasukan;
use App\Filament\Resources\VerifikasiWakilPemasukans\VerifikasiWakilPemasukanResource;
use App\Models\Pemasukan;
use App\Models\Periode;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\BukuAnggaran;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PemasukanVerifikasiTest extends TestCase
{
    use RefreshDatabase;

    protected User $pengguna;

    protected UnitKerja $unit;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filament.default_filesystem_disk'));

        $this->unit = UnitKerja::create(['name' => 'Fakultas Teknik']);
        $this->pengguna = User::factory()->create(['unit_kerja_id' => $this->unit->id]);

        $this->actingAs($this->pengguna);
    }

    protected function pemasukan(EnumStatusPemasukan $status = EnumStatusPemasukan::Draft): Pemasukan
    {
        return Pemasukan::factory()
            ->berstatus($status, $this->pengguna)
            ->create([
                'unit_kerja_id' => $this->unit->id,
                'user_id' => $this->pengguna->id,
                'nominal_pendapatan' => 3_000_000,
            ]);
    }

    /**
     * Satu berkas bukti tanda terima untuk mengisi modal unggahan.
     *
     * @return array<string, mixed>
     */
    protected function dataBukti(int $jumlah = 1): array
    {
        return [
            'bukti_path' => array_map(
                fn (int $i): UploadedFile => UploadedFile::fake()->create("bukti-{$i}.pdf", 50, 'application/pdf'),
                range(1, $jumlah),
            ),
        ];
    }

    public function test_pemasukan_baru_berstatus_draf(): void
    {
        $pemasukan = $this->pemasukan();

        $this->assertSame(EnumStatusPemasukan::Draft, $pemasukan->status);
        $this->assertTrue($pemasukan->dapatDiajukan());
        $this->assertTrue(PemasukanResource::canEdit($pemasukan));

        Livewire::test(ListPemasukans::class)
            ->assertTableActionVisible('ajukanPemasukan', $pemasukan);
    }

    public function test_unit_mengajukan_pemasukan(): void
    {
        $pemasukan = $this->pemasukan();

        Livewire::test(ListPemasukans::class)
            ->callAction(TestAction::make('ajukanPemasukan')->table($pemasukan))
            ->assertNotified('Pemasukan berhasil diajukan');

        $this->assertSame(EnumStatusPemasukan::Diajukan, $pemasukan->refresh()->status);
        $this->assertSame(1, $pemasukan->logs()->count());
        $this->assertSame(EnumStatusPemasukan::Diajukan, $pemasukan->logs()->first()->status);
    }

    public function test_alur_tiga_tahap_verifikasi_berjalan(): void
    {
        $pemasukan = $this->pemasukan(EnumStatusPemasukan::Diajukan);

        Livewire::test(ViewVerifikasiRektorPemasukan::class, ['record' => $pemasukan->getRouteKey()])
            ->callAction('setujuiPemasukan')
            ->assertHasNoActionErrors();

        $pemasukan->refresh();
        $this->assertSame(EnumStatusPemasukan::VerifikasiWakil, $pemasukan->status);
        $this->assertSame($this->pengguna->id, $pemasukan->rektor_id);
        $this->assertNotNull($pemasukan->disetujui_rektor_at);

        Livewire::test(ViewVerifikasiWakilPemasukan::class, ['record' => $pemasukan->getRouteKey()])
            ->callAction('setujuiPemasukan')
            ->assertHasNoActionErrors();

        $pemasukan->refresh();
        $this->assertSame(EnumStatusPemasukan::VerifikasiKeuangan, $pemasukan->status);
        $this->assertSame($this->pengguna->id, $pemasukan->wakil_id);
        $this->assertNotNull($pemasukan->disetujui_wakil_at);

        Livewire::test(ViewVerifikasiKeuanganPemasukan::class, ['record' => $pemasukan->getRouteKey()])
            ->callAction('setujuiPemasukan')
            ->assertHasNoActionErrors();

        $pemasukan->refresh();
        // Tahap terakhir tidak langsung memvalidasi: bola dikembalikan ke unit kerja.
        $this->assertSame(EnumStatusPemasukan::MenungguBukti, $pemasukan->status);
        $this->assertSame($this->pengguna->id, $pemasukan->keuangan_id);
        $this->assertNotNull($pemasukan->disetujui_keuangan_at);
    }

    public function test_unggah_bukti_membuat_pemasukan_valid(): void
    {
        $pemasukan = $this->pemasukan(EnumStatusPemasukan::MenungguBukti);

        Livewire::test(ListPemasukans::class)
            ->assertTableActionVisible('unggahBuktiPemasukan', $pemasukan)
            ->callAction(TestAction::make('unggahBuktiPemasukan')->table($pemasukan), $this->dataBukti())
            ->assertNotified('Pemasukan dinyatakan valid');

        $pemasukan->refresh();
        $this->assertSame(EnumStatusPemasukan::Valid, $pemasukan->status);
        $this->assertCount(1, $pemasukan->bukti_path);
        $this->assertNotNull($pemasukan->bukti_diserahkan_at);
        $this->assertNotNull($pemasukan->divalidasi_at);
    }

    public function test_revisi_kembali_ke_tahap_peminta_revisi(): void
    {
        $pemasukan = $this->pemasukan(EnumStatusPemasukan::VerifikasiWakil);

        Livewire::test(ViewVerifikasiWakilPemasukan::class, ['record' => $pemasukan->getRouteKey()])
            ->callAction('revisiPemasukan', ['catatan' => '<p>Nominal tidak sesuai kuitansi.</p>'])
            ->assertHasNoActionErrors();

        $pemasukan->refresh();
        $this->assertSame(EnumStatusPemasukan::Revisi, $pemasukan->status);
        $this->assertStringContainsString('kuitansi', (string) $pemasukan->catatan_verifikasi);
        $this->assertSame('Revisi Wakil Rektor', $pemasukan->labelStatus());

        Livewire::test(ListPemasukans::class)
            ->callAction(TestAction::make('ajukanPemasukan')->table($pemasukan))
            ->assertNotified('Pemasukan berhasil diajukan');

        $pemasukan->refresh();
        // Verifikasi yang sudah lewat tidak diulang: langsung kembali ke Wakil Rektor.
        $this->assertSame(EnumStatusPemasukan::VerifikasiWakil, $pemasukan->status);
        $this->assertNull($pemasukan->catatan_verifikasi);
    }

    public function test_penolakan_bersifat_final(): void
    {
        $pemasukan = $this->pemasukan(EnumStatusPemasukan::Diajukan);

        Livewire::test(ViewVerifikasiRektorPemasukan::class, ['record' => $pemasukan->getRouteKey()])
            ->callAction('tolakPemasukan', ['catatan' => '<p>Bukan pemasukan unit.</p>'])
            ->assertHasNoActionErrors();

        $pemasukan->refresh();
        $this->assertSame(EnumStatusPemasukan::Ditolak, $pemasukan->status);
        $this->assertFalse($pemasukan->dapatDiajukan());

        Livewire::test(ListPemasukans::class)
            ->assertTableActionHidden('ajukanPemasukan', $pemasukan);
    }

    public function test_pemasukan_tidak_valid_tidak_masuk_buku_anggaran(): void
    {
        $periode = Periode::create(['name' => 'P1', 'start_datetime' => now()->startOfYear(), 'end_datetime' => now()->endOfYear()]);
        TahunKerja::create([
            'periode_id' => $periode->id,
            'name' => 'TA '.now()->year,
            'start_datetime' => now()->startOfYear(),
            'end_datetime' => now()->endOfYear(),
            'status' => EnumStatusTahunKerja::Berjalan,
        ]);

        $tanggal = now()->toDateString();

        $this->pemasukan(EnumStatusPemasukan::Valid)->update(['tanggal_pelaksanaan' => $tanggal]);
        $this->pemasukan(EnumStatusPemasukan::VerifikasiWakil)->update(['tanggal_pelaksanaan' => $tanggal]);
        $this->pemasukan(EnumStatusPemasukan::Ditolak)->update(['tanggal_pelaksanaan' => $tanggal]);

        $pemasukanTercatat = BukuAnggaran::untukUnit($this->unit->id)->ringkasan()['pemasukan'] ?? null;

        // Hanya satu pemasukan berstatus Valid yang boleh terhitung.
        $this->assertSame(3_000_000.0, (float) $pemasukanTercatat);
    }

    public function test_pemasukan_tidak_dapat_diubah_setelah_diajukan(): void
    {
        $draf = $this->pemasukan();
        $diajukan = $this->pemasukan(EnumStatusPemasukan::Diajukan);

        $this->assertTrue(PemasukanResource::canEdit($draf));
        $this->assertTrue(PemasukanResource::canDelete($draf));

        $this->assertFalse(PemasukanResource::canEdit($diajukan));
        $this->assertFalse(PemasukanResource::canDelete($diajukan));

        // Tombol Ubah pada halaman detail ikut hilang begitu pemasukan diajukan.
        Livewire::test(ViewPemasukan::class, ['record' => $draf->getRouteKey()])
            ->assertActionVisible('edit');

        Livewire::test(ViewPemasukan::class, ['record' => $diajukan->getRouteKey()])
            ->assertActionHidden('edit');
    }

    public function test_verifikator_tanpa_permission_tidak_melihat_aksi(): void
    {
        $pemasukan = $this->pemasukan(EnumStatusPemasukan::Diajukan);

        $this->assertTrue($this->bolehMemutuskan($pemasukan));

        // Pemasukan yang tidak sedang menunggu keputusan tahap mana pun tidak memberi
        // wewenang kepada siapa pun.
        $pemasukan->update(['status' => EnumStatusPemasukan::Valid]);
        $this->assertFalse($this->bolehMemutuskan($pemasukan->refresh()));

        $pemasukan->update(['status' => EnumStatusPemasukan::Draft]);
        $this->assertFalse($this->bolehMemutuskan($pemasukan->refresh()));
    }

    public function test_setiap_tab_menampilkan_antrean_yang_benar(): void
    {
        $diRektor = $this->pemasukan(EnumStatusPemasukan::Diajukan);
        $diWakil = $this->pemasukan(EnumStatusPemasukan::VerifikasiWakil);
        $diKeuangan = $this->pemasukan(EnumStatusPemasukan::VerifikasiKeuangan);

        // Antrean tiap tahap hanya berisi pemasukan pada tahap itu.
        $this->assertEqualsCanonicalizing(
            [$diRektor->id],
            VerifikasiRektorPemasukanResource::pendingStageQuery()->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$diWakil->id],
            VerifikasiWakilPemasukanResource::pendingStageQuery()->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$diKeuangan->id],
            VerifikasiKeuanganPemasukanResource::pendingStageQuery()->pluck('id')->all(),
        );

        // Tahap Rektor sudah merespon dua pemasukan yang kini berada di tahap lanjutan.
        $this->assertEqualsCanonicalizing(
            [$diWakil->id, $diKeuangan->id],
            VerifikasiRektorPemasukanResource::respondedStageQuery()->pluck('id')->all(),
        );

        $ditolak = $this->pemasukan(EnumStatusPemasukan::Diajukan);
        $ditolak->update(['status' => EnumStatusPemasukan::Ditolak, 'rektor_id' => $this->pengguna->id]);

        $this->assertEqualsCanonicalizing(
            [$ditolak->id],
            VerifikasiRektorPemasukanResource::rejectedStageQuery()->pluck('id')->all(),
        );

        Livewire::test(ListVerifikasiRektorPemasukans::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$diRektor]);
    }

    public function test_antrean_dapat_disaring_waktu_pencatatan_dan_periode_pelaksanaan(): void
    {
        // created_at bukan atribut fillable, jadi diisi lewat forceFill.
        $lama = $this->pemasukan(EnumStatusPemasukan::Diajukan);
        $lama->forceFill(['tanggal_pelaksanaan' => '2026-01-10', 'created_at' => '2026-01-11 08:00:00'])->save();

        $baru = $this->pemasukan(EnumStatusPemasukan::Diajukan);
        $baru->forceFill(['tanggal_pelaksanaan' => '2026-05-20', 'created_at' => '2026-05-21 08:00:00'])->save();

        // Kedua penyaring rentang waktu harus berdiri sendiri — bukan saling menimpa.
        Livewire::test(ListVerifikasiRektorPemasukans::class)
            ->assertCanSeeTableRecords([$lama, $baru])
            ->filterTable('waktu_pengajuan', ['dari' => '2026-05-01', 'sampai' => '2026-05-31'])
            ->assertCanSeeTableRecords([$baru])
            ->assertCanNotSeeTableRecords([$lama])
            ->resetTableFilters()
            ->filterTable('periode_pelaksanaan', ['dari' => '2026-01-01', 'sampai' => '2026-01-31'])
            ->assertCanSeeTableRecords([$lama])
            ->assertCanNotSeeTableRecords([$baru]);
    }

    /**
     * Membaca `bolehMemutuskan()` yang bersifat protected pada induk aksi verifikasi.
     */
    protected function bolehMemutuskan(Pemasukan $record): bool
    {
        $method = new \ReflectionMethod(TahapVerifikasiPemasukanAction::class, 'bolehMemutuskan');

        return $method->invoke(null, $record);
    }
}
