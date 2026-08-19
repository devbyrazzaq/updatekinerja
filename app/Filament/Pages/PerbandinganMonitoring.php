<?php

namespace App\Filament\Pages;

use App\Exports\PerbandinganMonitoringExport;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Pages\Concerns\HasPageAuthorization;
use App\Filament\Pages\Concerns\MemilihCakupanLaporan;
use App\Filament\Pages\Widgets\PerbandinganNominalChart;
use App\Filament\Pages\Widgets\PerbandinganPersentaseChart;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Reports\TabularReport;
use App\Services\KonteksProgramKerja;
use App\Services\MonitoringAnggaran;
use App\Services\PermissionRegistrar;
use App\Services\RingkasanMonitoring;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Membandingkan capaian dan penyerapan anggaran secara berdampingan, dalam dua mode
 * yang menjawab dua pertanyaan berbeda:
 *
 * - Mode Antar Unit Kerja menyandingkan beberapa unit pada satu tahun kerja — dipakai
 *   menilai unit mana yang paling menyerap pagunya dan paling mencapai targetnya.
 * - Mode Antar Tahun Kerja menyandingkan beberapa tahun untuk satu cakupan yang sama —
 *   dipakai melihat arah gerak dari tahun ke tahun.
 *
 * Keduanya memakai angka yang sama dengan {@see MonitoringProgramKerja}, sehingga
 * kolom mana pun di sini akan cocok bila ditelusuri di halaman monitoring.
 *
 * @property-read Schema $form
 */
class PerbandinganMonitoring extends Page
{
    use HasPageAuthorization;
    use MemilihCakupanLaporan;

    /**
     * Mode membandingkan beberapa unit kerja pada satu tahun kerja.
     */
    public const MODE_UNIT = 'unit';

    /**
     * Mode membandingkan beberapa tahun kerja pada satu cakupan unit kerja.
     */
    public const MODE_TAHUN = 'tahun';

    /**
     * Banyak pembanding yang dipasang saat halaman pertama dibuka.
     */
    protected const PEMBANDING_AWAL = 3;

    protected string $view = 'filament.pages.perbandingan-monitoring';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Perbandingan Monitoring';

    protected static ?string $title = 'Perbandingan Monitoring';

    protected static ?string $slug = 'perbandingan-monitoring';

    protected static ?int $navigationSort = 4;

    /**
     * Metadata untuk form Role & Hak Akses (dibaca PermissionRegistrar).
     *
     * @var array<string, mixed>
     */
    public static array $permissions = [
        'heading' => 'Perbandingan Monitoring',
        'description' => 'Hak akses untuk membandingkan penyerapan anggaran dan capaian program kerja antar unit kerja maupun antar tahun kerja.',
        'permission_descriptions' => [
            'view_page_perbandingan_monitoring' => 'Membandingkan penyerapan anggaran dan capaian antar unit kerja atau antar tahun kerja.',
        ],
    ];

    /**
     * Penyaring tampilan halaman.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Mode perbandingan yang sedang dipakai.
     */
    public string $mode = self::MODE_UNIT;

    /**
     * Tahun kerja pembanding pada mode Antar Unit Kerja.
     */
    public ?int $tahunKerjaId = null;

    /**
     * Unit kerja yang disandingkan pada mode Antar Unit Kerja.
     *
     * @var array<int, int>
     */
    public array $unitKerjaIds = [];

    /**
     * Cakupan unit kerja pada mode Antar Tahun Kerja. Null berarti seluruh unit yang
     * boleh diakses, sehingga yang dibandingkan adalah gerak lembaga secara utuh.
     */
    public ?int $unitKerjaId = null;

    /**
     * Tahun kerja yang disandingkan pada mode Antar Tahun Kerja.
     *
     * @var array<int, int>
     */
    public array $tahunKerjaIds = [];

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $kolom = null;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Monitoring';
    }

    public function mount(): void
    {
        $this->tahunKerjaId = KonteksProgramKerja::tahunBerjalan()?->getKey()
            ?? array_key_first($this->tahunKerjaOptions());

        $this->unitKerjaIds = array_slice(array_keys($this->unitKerjaOptions()), 0, self::PEMBANDING_AWAL);
        $this->tahunKerjaIds = array_slice(array_keys($this->tahunKerjaOptions()), 0, self::PEMBANDING_AWAL);

        $this->form->fill([
            'mode' => $this->mode,
            'tahunKerjaId' => $this->tahunKerjaId,
            'unitKerjaIds' => $this->unitKerjaIds,
            'unitKerjaId' => null,
            'tahunKerjaIds' => $this->tahunKerjaIds,
        ]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->mode === self::MODE_UNIT
            ? 'Menyandingkan beberapa unit kerja pada satu tahun kerja yang sama.'
            : 'Menyandingkan beberapa tahun kerja untuk satu cakupan unit kerja yang sama.';
    }

    /**
     * @return array<int, class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            PerbandinganNominalChart::class,
            PerbandinganPersentaseChart::class,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return ['kolom' => $this->kolom()];
    }

    /**
     * Ekspor menyalin matriks yang sedang tampil — kolom pembandingnya mengikuti mode
     * dan pilihan pengguna, jadi kelas ekspornya dibentuk langsung dari keadaan
     * halaman alih-alih diresolve dari container.
     *
     * Laporan PDF menanyakan cakupan unit kerja lebih dahulu, tetapi hanya pada mode
     * Antar Tahun Kerja: pada mode Antar Unit Kerja unit-unit itulah yang menjadi
     * kolom matriksnya, sehingga cakupannya sudah ditentukan penyaring halaman dan
     * modalnya dimatikan.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ExcelExportAction::make()
                ->permission(static::getPagePermission())
                ->visible(fn (): bool => $this->kolom() !== [])
                ->action(fn () => $this->export($this->unitKerjaId)->download()),
            PdfReportAction::make()
                ->permission(static::getPagePermission())
                ->visible(fn (): bool => $this->kolom() !== [])
                ->cakupan($this->skemaCakupanLaporan(fn (): ?int => $this->unitKerjaId))
                ->modal(fn (): bool => $this->mode === self::MODE_TAHUN)
                ->action(fn (array $data) => (new TabularReport($this->export(
                    $this->mode === self::MODE_TAHUN ? $this->cakupanUnitKerja($data) : $this->unitKerjaId,
                )))->download()),
        ];
    }

    /**
     * @param  int|null  $unitKerjaId  Cakupan mode Antar Tahun Kerja; null berarti seluruh unit kerja.
     */
    protected function export(?int $unitKerjaId): PerbandinganMonitoringExport
    {
        return new PerbandinganMonitoringExport(
            kolom: $this->mode === self::MODE_TAHUN
                ? $this->kolomTahunKerja($unitKerjaId)
                : $this->kolomUnitKerja(),
            metrik: $this->metrik(),
            cakupan: $this->mode === self::MODE_UNIT
                ? 'Antar unit kerja pada satu tahun kerja yang sama.'
                : 'Antar tahun kerja pada '.$this->namaCakupan($unitKerjaId).'.',
        );
    }

    /**
     * Sebutan cakupan unit kerja untuk keterangan laporan.
     */
    protected function namaCakupan(?int $unitKerjaId): string
    {
        if ($unitKerjaId === null) {
            return 'seluruh unit kerja';
        }

        return $this->unitKerjaOptions()[$unitKerjaId] ?? 'unit kerja terpilih';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bandingkan')
                    ->description('Pilih apa yang ingin disandingkan, lalu tentukan pembandingnya.')
                    ->icon(Heroicon::OutlinedScale)
                    ->collapsible()
                    ->schema([
                        ToggleButtons::make('mode')
                            ->label('Mode Perbandingan')
                            ->options([
                                self::MODE_UNIT => 'Antar Unit Kerja',
                                self::MODE_TAHUN => 'Antar Tahun Kerja',
                            ])
                            ->icons([
                                self::MODE_UNIT => Heroicon::OutlinedBuildingOffice2,
                                self::MODE_TAHUN => Heroicon::OutlinedCalendarDays,
                            ])
                            ->inline()
                            ->live()
                            ->afterStateUpdated(function (mixed $state): void {
                                $this->mode = $state === self::MODE_TAHUN ? self::MODE_TAHUN : self::MODE_UNIT;
                                $this->kolom = null;
                            }),
                        Grid::make(2)->schema([
                            Select::make('tahunKerjaId')
                                ->label('Tahun Kerja')
                                ->options($this->tahunKerjaOptions())
                                ->searchable()
                                ->native(false)
                                ->selectablePlaceholder(false)
                                ->live()
                                ->visible(fn (Get $get): bool => $get('mode') !== self::MODE_TAHUN)
                                ->afterStateUpdated(function (mixed $state): void {
                                    $this->tahunKerjaId = filled($state) ? (int) $state : null;
                                    $this->kolom = null;
                                })
                                ->helperText('Seluruh unit kerja dibandingkan pada tahun kerja ini.')
                                ->columnSpan(1),
                            Select::make('unitKerjaIds')
                                ->label('Unit Kerja yang Dibandingkan')
                                ->options($this->unitKerjaOptions())
                                ->multiple()
                                ->searchable()
                                ->native(false)
                                ->live()
                                ->visible(fn (Get $get): bool => $get('mode') !== self::MODE_TAHUN)
                                ->afterStateUpdated(function (mixed $state): void {
                                    $this->unitKerjaIds = $this->idsTerpilih($state);
                                    $this->kolom = null;
                                })
                                ->helperText('Pilih dua unit kerja atau lebih untuk disandingkan.')
                                ->columnSpan(1),
                            Select::make('unitKerjaId')
                                ->label('Unit Kerja')
                                ->options($this->unitKerjaOptions())
                                ->placeholder('Seluruh Unit Kerja')
                                ->searchable()
                                ->native(false)
                                ->live()
                                ->visible(fn (Get $get): bool => $get('mode') === self::MODE_TAHUN)
                                ->afterStateUpdated(function (mixed $state): void {
                                    $this->unitKerjaId = filled($state) ? (int) $state : null;
                                    $this->kolom = null;
                                })
                                ->helperText('Kosongkan untuk membandingkan seluruh unit kerja sekaligus.')
                                ->columnSpan(1),
                            Select::make('tahunKerjaIds')
                                ->label('Tahun Kerja yang Dibandingkan')
                                ->options($this->tahunKerjaOptions())
                                ->multiple()
                                ->searchable()
                                ->native(false)
                                ->live()
                                ->visible(fn (Get $get): bool => $get('mode') === self::MODE_TAHUN)
                                ->afterStateUpdated(function (mixed $state): void {
                                    $this->tahunKerjaIds = $this->idsTerpilih($state);
                                    $this->kolom = null;
                                })
                                ->helperText('Pilih dua tahun kerja atau lebih untuk disandingkan.')
                                ->columnSpan(1),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Kolom pembanding beserta seluruh angkanya, urut sesuai pilihan pengguna.
     *
     * @return array<int, array<string, mixed>>
     */
    public function kolom(): array
    {
        return $this->kolom ??= $this->mode === self::MODE_TAHUN
            ? $this->kolomTahunKerja($this->unitKerjaId)
            : $this->kolomUnitKerja();
    }

    /**
     * Baris matriks perbandingan: nama metrik, cara membacanya, dan kunci nilainya
     * pada tiap kolom. Urutannya mengikuti alur uang — dari pagu yang tersedia,
     * turun ke yang terserap, lalu ke hasil yang dicapai.
     *
     * @return array<int, array{label: string, kunci: string, format: string, keterangan: string}>
     */
    public function metrik(): array
    {
        return [
            ['label' => 'Pagu Anggaran', 'kunci' => 'pagu', 'format' => 'rupiah', 'keterangan' => 'Anggaran yang ditetapkan'],
            ['label' => 'Anggaran Terserap', 'kunci' => 'terserap', 'format' => 'rupiah', 'keterangan' => 'Sudah dicairkan, dikoreksi penyelesaian selisih'],
            ['label' => 'Menunggu Pencairan', 'kunci' => 'komitmen', 'format' => 'rupiah', 'keterangan' => 'Sudah diajukan, belum cair'],
            ['label' => 'Sisa Pagu', 'kunci' => 'sisa_pagu', 'format' => 'rupiah', 'keterangan' => 'Pagu dikurangi yang terserap'],
            ['label' => 'Penyerapan Anggaran', 'kunci' => 'persentase_penyerapan', 'format' => 'persen', 'keterangan' => 'Porsi pagu yang terserap'],
            ['label' => 'Program Kerja Ditawarkan', 'kunci' => 'jumlah_program', 'format' => 'angka', 'keterangan' => 'Program kerja aktif pada tahun tersebut'],
            ['label' => 'Program Kerja Dilaksanakan', 'kunci' => 'jumlah_program_diajukan', 'format' => 'angka', 'keterangan' => 'Program kerja yang sudah diajukan'],
            ['label' => 'Realisasi Selesai', 'kunci' => 'jumlah_selesai', 'format' => 'angka', 'keterangan' => 'Realisasi yang laporannya sudah disetujui'],
            ['label' => 'Capaian Target', 'kunci' => 'capaian', 'format' => 'persen', 'keterangan' => 'Rata-rata ketercapaian target program kerja'],
        ];
    }

    /**
     * Satu kolom per unit kerja terpilih pada tahun kerja yang sama.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function kolomUnitKerja(): array
    {
        $tahunKerja = $this->tahunKerjaId !== null ? TahunKerja::find($this->tahunKerjaId) : null;
        $nama = $this->unitKerjaOptions();

        return collect($this->unitKerjaIds)
            ->filter(fn (int $unitKerjaId): bool => isset($nama[$unitKerjaId]))
            ->map(fn (int $unitKerjaId): array => MonitoringAnggaran::untukUnit($unitKerjaId, $tahunKerja)
                ->ringkasan($nama[$unitKerjaId])
                ->toArray())
            ->values()
            ->all();
    }

    /**
     * Satu kolom per tahun kerja terpilih pada cakupan unit kerja yang sama.
     *
     * @param  int|null  $unitKerjaId  Cakupannya; null berarti seluruh unit kerja yang boleh diakses.
     * @return array<int, array<string, mixed>>
     */
    protected function kolomTahunKerja(?int $unitKerjaId): array
    {
        $unitKerjaIds = $unitKerjaId !== null
            ? [$unitKerjaId]
            : array_keys($this->unitKerjaOptions());

        $tahunKerja = TahunKerja::query()
            ->whereIn('id', $this->tahunKerjaIds)
            ->get()
            ->keyBy(fn (TahunKerja $tahun): int => (int) $tahun->getKey());

        return collect($this->tahunKerjaIds)
            ->filter(fn (int $tahunKerjaId): bool => $tahunKerja->has($tahunKerjaId))
            ->map(fn (int $tahunKerjaId): array => MonitoringAnggaran::untukUnits($unitKerjaIds, $tahunKerja->get($tahunKerjaId))
                ->ringkasan($tahunKerja->get($tahunKerjaId)->name)
                ->toArray())
            ->values()
            ->all();
    }

    /**
     * Kolom terkuat untuk sebuah metrik, dipakai menandai pemenang di tiap baris
     * matriks. Metrik yang seluruh kolomnya kosong tidak punya pemenang.
     */
    public function kolomTerbaik(string $kunci): ?int
    {
        $terbaik = null;
        $tertinggi = null;

        foreach ($this->kolom() as $urutan => $kolom) {
            $nilai = $kolom[$kunci] ?? null;

            if ($nilai === null || $nilai <= 0) {
                continue;
            }

            if ($tertinggi === null || $nilai > $tertinggi) {
                $tertinggi = $nilai;
                $terbaik = $urutan;
            }
        }

        return $terbaik;
    }

    /**
     * Ringkasan gabungan seluruh kolom, dipakai sebagai baris total matriks.
     */
    public function total(): RingkasanMonitoring
    {
        return RingkasanMonitoring::gabung(
            collect($this->kolom())->map(fn (array $kolom): RingkasanMonitoring => new RingkasanMonitoring(
                unitKerjaId: $kolom['unit_kerja_id'],
                label: $kolom['label'],
                pagu: (float) $kolom['pagu'],
                pencairan: (float) $kolom['pencairan'],
                sisaDikembalikan: (float) $kolom['sisa_dikembalikan'],
                kekuranganDilunasi: (float) $kolom['kekurangan_dilunasi'],
                komitmen: (float) $kolom['komitmen'],
                jumlahProgram: (int) $kolom['jumlah_program'],
                jumlahProgramDiajukan: (int) $kolom['jumlah_program_diajukan'],
                jumlahPengajuan: (int) $kolom['jumlah_pengajuan'],
                jumlahRealisasi: (int) $kolom['jumlah_realisasi'],
                jumlahSelesai: (int) $kolom['jumlah_selesai'],
                capaian: $kolom['capaian'],
            )),
            label: 'Total',
        );
    }

    /**
     * Membersihkan pilihan multiselect menjadi daftar id bertipe int.
     *
     * @return array<int, int>
     */
    protected function idsTerpilih(mixed $state): array
    {
        return array_values(array_map(intval(...), array_filter((array) $state, filled(...))));
    }

    /**
     * @return array<int, string>
     */
    protected function tahunKerjaOptions(): array
    {
        return TahunKerja::query()
            ->orderByDesc('start_datetime')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function unitKerjaOptions(): array
    {
        $query = UnitKerja::query()->where('is_active', true)->orderBy('name');

        $user = auth()->user();

        if ($user !== null && ! $user->isPrivileged()) {
            $query->whereIn('id', PermissionRegistrar::permittedUnitIds($user)->all());
        }

        return $query->pluck('name', 'id')->all();
    }
}
