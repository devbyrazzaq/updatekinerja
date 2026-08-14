<?php

namespace Tests\Feature;

use App\Enums\EnumStatusTahunKerja;
use App\Filament\Resources\DaftarProgramKerjas\DaftarProgramKerjaResource;
use App\Filament\Resources\DaftarProgramKerjas\Pages\ListDaftarProgramKerjas;
use App\Filament\Resources\DaftarProgramKerjas\Widgets\DaftarProgramKerjaOverview;
use App\Models\Bidang;
use App\Models\Kategori;
use App\Models\PaguAnggaran;
use App\Models\PenawaranProgramKerja;
use App\Models\Periode;
use App\Models\Program;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DaftarProgramKerjaOverviewTest extends TestCase
{
    use RefreshDatabase;

    private TahunKerja $tahunKerja;

    private UnitKerja $unitA;

    private UnitKerja $unitB;

    private PenawaranProgramKerja $penawaranA;

    private PenawaranProgramKerja $penawaranB;

    protected function setUp(): void
    {
        parent::setUp();

        $periode = Periode::create(['name' => 'P1', 'start_datetime' => now(), 'end_datetime' => now()->addYear()]);
        $this->tahunKerja = TahunKerja::create(['periode_id' => $periode->id, 'name' => 'TA 2026', 'start_datetime' => now(), 'end_datetime' => now()->addYear(), 'status' => EnumStatusTahunKerja::Berjalan]);
        $bidang = Bidang::create(['code' => 'B1', 'name' => 'Akademik']);
        $kategori = Kategori::create(['code' => 'K1', 'name' => 'Pendidikan']);
        $program = Program::create(['name' => 'Tridharma']);

        $this->unitA = UnitKerja::create(['name' => 'Unit A']);
        $this->unitB = UnitKerja::create(['name' => 'Unit B']);

        PaguAnggaran::create(['tahun_kerja_id' => $this->tahunKerja->id, 'unit_kerja_id' => $this->unitA->id, 'amount' => 5000000]);
        PaguAnggaran::create(['tahun_kerja_id' => $this->tahunKerja->id, 'unit_kerja_id' => $this->unitB->id, 'amount' => 3000000]);

        $this->penawaranA = $this->makePenawaran($this->unitA, $bidang, $kategori, $program, 'Prokerja A');
        $this->penawaranB = $this->makePenawaran($this->unitB, $bidang, $kategori, $program, 'Prokerja B');

        $user = User::factory()->create(['unit_kerja_id' => null]);
        $user->givePermissionTo(Permission::findOrCreate('bypass_data_scope', 'web'));

        $this->actingAs($user);
    }

    private function makePenawaran(UnitKerja $unit, Bidang $b, Kategori $k, Program $p, string $name): PenawaranProgramKerja
    {
        return PenawaranProgramKerja::create([
            'tahun_kerja_id' => $this->tahunKerja->id,
            'unit_kerja_id' => $unit->id,
            'bidang_id' => $b->id,
            'kategori_id' => $k->id,
            'program_id' => $p->id,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    public function test_table_shows_unit_kerja_column_and_all_units_by_default(): void
    {
        Livewire::test(ListDaftarProgramKerjas::class)
            ->assertCanRenderTableColumn('unitKerja.name')
            ->assertCanSeeTableRecords([$this->penawaranA, $this->penawaranB]);
    }

    public function test_select_filters_table_to_chosen_unit(): void
    {
        Livewire::test(ListDaftarProgramKerjas::class)
            ->set('unitKerjaId', $this->unitA->id)
            ->assertCanSeeTableRecords([$this->penawaranA])
            ->assertCanNotSeeTableRecords([$this->penawaranB]);
    }

    public function test_widget_aggregates_all_units_when_no_selection(): void
    {
        Livewire::test(DaftarProgramKerjaOverview::class, ['unitKerjaId' => null])
            ->assertOk()
            ->assertSee('Semua Unit')
            ->assertSee('Rp 8.000.000')
            ->assertSee('Total Program Kerja');
    }

    public function test_widget_reflects_selected_unit(): void
    {
        Livewire::test(DaftarProgramKerjaOverview::class, ['unitKerjaId' => $this->unitA->id])
            ->assertOk()
            ->assertSee('Unit A')
            ->assertSee('Rp 5.000.000');
    }

    public function test_widget_lists_covered_units_when_showing_all(): void
    {
        Livewire::test(DaftarProgramKerjaOverview::class, ['unitKerjaId' => null])
            ->assertOk()
            ->assertSee('Semua Unit')
            ->assertSee('2 unit: Unit A, Unit B');
    }

    /**
     * Cakupan satu unit langsung disebut namanya, bukan diringkas jadi "Semua Unit".
     */
    public function test_widget_names_the_unit_when_scope_covers_only_one(): void
    {
        $this->actingAs(User::factory()->create(['unit_kerja_id' => $this->unitA->id]));

        Livewire::test(DaftarProgramKerjaOverview::class, ['unitKerjaId' => null])
            ->assertOk()
            ->assertSee('Unit A')
            ->assertDontSee('Semua Unit')
            ->assertSee('Rp 5.000.000');
    }

    public function test_page_defaults_the_filter_to_the_first_permitted_unit(): void
    {
        $this->actingAs(User::factory()->create(['unit_kerja_id' => $this->unitB->id]));

        Livewire::test(ListDaftarProgramKerjas::class)
            ->assertSet('unitKerjaId', $this->unitB->id)
            ->assertCanSeeTableRecords([$this->penawaranB])
            ->assertCanNotSeeTableRecords([$this->penawaranA]);
    }

    /**
     * Pengguna yang berwenang atas seluruh data tetap memulai dari gabungan unit.
     */
    public function test_page_keeps_all_units_for_privileged_user(): void
    {
        Livewire::test(ListDaftarProgramKerjas::class)
            ->assertSet('unitKerjaId', null);
    }

    /**
     * Urutan tampilan halaman: penyaring, ringkasan, lalu tabel.
     */
    public function test_page_renders_filter_then_overview_then_table(): void
    {
        $html = Livewire::test(ListDaftarProgramKerjas::class)->html();

        $penyaring = strpos($html, 'Tampilkan Data');
        $ringkasan = strpos($html, 'wire:name="'.DaftarProgramKerjaOverview::class.'"');
        $tabel = strpos($html, 'Prokerja A');

        $this->assertNotFalse($penyaring);
        $this->assertNotFalse($ringkasan);
        $this->assertNotFalse($tabel);
        $this->assertLessThan($ringkasan, $penyaring);
        $this->assertLessThan($tabel, $ringkasan);
    }

    public function test_navigation_badge_counts_offered_program_kerja(): void
    {
        $this->assertSame('2', DaftarProgramKerjaResource::getNavigationBadge());
    }
}
