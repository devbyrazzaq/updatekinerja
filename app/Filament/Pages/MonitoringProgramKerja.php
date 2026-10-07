<?php

namespace App\Filament\Pages;

use App\Enums\EnumStatusRealisasi;
use App\Exports\MonitoringProgramKerjasExport;
use App\Filament\Actions\CatatCapaianProgramKerjaAction;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\ImporCapaianProgramKerjaAction;
use App\Filament\Actions\MediaAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Pages\Concerns\HasFilterAboveWidgets;
use App\Filament\Pages\Concerns\HasPageAuthorization;
use App\Filament\Pages\Concerns\MemilihCakupanLaporan;
use App\Filament\Pages\Widgets\CapaianProgramKerjaChart;
use App\Filament\Pages\Widgets\DistribusiAnggaranChart;
use App\Filament\Pages\Widgets\MonitoringOverview;
use App\Filament\Pages\Widgets\PenyerapanAnggaranChart;
use App\Models\Bidang;
use App\Models\Program;
use App\Models\RealisasiProgramKerja;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Reports\TabularReport;
use App\Services\KonteksProgramKerja;
use App\Services\MonitoringAnggaran;
use App\Services\PermissionRegistrar;
use App\Services\RingkasanMonitoring;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Monitoring program kerja satu tahun kerja: berapa pagu yang tersedia, berapa yang
 * sudah terserap, ke mana anggarannya mengalir, dan seberapa jauh target program
 * kerjanya tercapai.
 *
 * Penyerapan memakai definisi Buku Anggaran — berbasis kas, dihitung sejak anggaran
 * benar-benar dicairkan lalu dikoreksi penyelesaian selisihnya. Angka "menunggu cair"
 * ditampilkan terpisah agar anggaran yang sudah mengikat pagu tetap terlihat tanpa
 * mencampuradukkannya dengan yang sudah keluar.
 *
 * Penyaring unit kerja boleh dikosongkan; tanpa unit terpilih halaman merangkum
 * seluruh unit kerja yang boleh diakses pengguna. Untuk rekap berdampingan seluruh
 * unit, lihat {@see RingkasanUnitKerja}; untuk membandingkan beberapa unit atau
 * beberapa tahun, lihat {@see PerbandinganMonitoring}.
 *
 * @property-read Schema $form
 */
class MonitoringProgramKerja extends Page implements HasTable
{
    use HasFilterAboveWidgets;
    use HasPageAuthorization;
    use InteractsWithTable;
    use MemilihCakupanLaporan;

    protected string $view = 'filament.pages.monitoring-program-kerja';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Monitoring Program Kerja';

    protected static ?string $title = 'Monitoring Program Kerja';

    protected static ?string $slug = 'monitoring-program-kerja';

    protected static ?int $navigationSort = 1;

    /**
     * Hak akses mencatat capaian langsung dari halaman ini. Dipisahkan dari hak akses
     * halaman karena memantau tidak dengan sendirinya berarti boleh mengubah capaian.
     */
    public const PERMISSION_CATAT_CAPAIAN = 'update_capaian_monitoring_program_kerja';

    /**
     * Metadata untuk form Role & Hak Akses (dibaca PermissionRegistrar).
     *
     * @var array<string, mixed>
     */
    public static array $permissions = [
        'heading' => 'Monitoring Program Kerja',
        'description' => 'Hak akses untuk memantau penyerapan anggaran, distribusinya, dan capaian program kerja per unit kerja.',
        'permission_descriptions' => [
            'view_page_monitoring_program_kerja' => 'Membuka monitoring penyerapan anggaran dan capaian program kerja.',
            self::PERMISSION_CATAT_CAPAIAN => 'Mencatat capaian program kerja beserta laporannya langsung dari halaman monitoring, tanpa anggaran.',
        ],
    ];

    /**
     * @return array<string, string>
     */
    public static function getPermissionDefinitions(): array
    {
        return [
            static::getPagePermission() => 'Akses Halaman',
            self::PERMISSION_CATAT_CAPAIAN => 'Catat Capaian',
        ];
    }

    /**
     * Penyaring tampilan halaman.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Tahun kerja yang dipantau. Bawaannya tahun kerja berjalan.
     */
    public ?int $tahunKerjaId = null;

    /**
     * Unit kerja yang dipantau. Null berarti seluruh unit yang boleh diakses.
     */
    public ?int $unitKerjaId = null;

    private ?MonitoringAnggaran $monitoring = null;

    /**
     * @var array<int, array<int, string>>|null penawaran id => path berkas laporan
     */
    private ?array $laporanPerProgram = null;

    private ?TahunKerja $tahunKerja = null;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Monitoring';
    }

    public function mount(): void
    {
        $this->tahunKerjaId = KonteksProgramKerja::tahunBerjalan()?->getKey();

        $this->form->fill([
            'tahunKerjaId' => $this->tahunKerjaId,
            'unitKerjaId' => null,
        ]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        $tahunKerja = $this->tahunKerjaTerpilih();

        if ($tahunKerja === null) {
            return 'Tahun kerja belum ditetapkan. Pilih tahun kerja pada penyaring untuk mulai memantau.';
        }

        $unitKerja = $this->unitKerjaId === null
            ? 'seluruh unit kerja'
            : ($this->unitKerjaOptions()[$this->unitKerjaId] ?? 'unit kerja terpilih');

        return 'Penyerapan anggaran dan capaian program kerja '.$unitKerja.' pada '.$tahunKerja->name
            .'. Anggaran dihitung terserap sejak benar-benar dicairkan, sama seperti Buku Anggaran.';
    }

    /**
     * @return array<int, class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            MonitoringOverview::class,
            PenyerapanAnggaranChart::class,
            DistribusiAnggaranChart::class,
            CapaianProgramKerjaChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            'unitKerjaId' => $this->unitKerjaId,
            'tahunKerjaId' => $this->tahunKerjaId,
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tampilkan Data')
                    ->description('Pilih tahun kerja yang dipantau. Biarkan unit kerja kosong untuk merangkum seluruh unit yang boleh diakses.')
                    ->icon(Heroicon::OutlinedFunnel)
                    ->collapsible()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('tahunKerjaId')
                                ->label('Tahun Kerja')
                                ->options($this->tahunKerjaOptions())
                                ->searchable()
                                ->native(false)
                                ->selectablePlaceholder(false)
                                ->live()
                                ->afterStateUpdated(function (mixed $state): void {
                                    $this->tahunKerjaId = filled($state) ? (int) $state : null;
                                    $this->tahunKerja = null;
                                    $this->segarkanMonitoring();
                                })
                                ->helperText('Bawaannya tahun kerja berjalan; pilih tahun lain untuk memantau tahun sebelumnya.')
                                ->columnSpan(1),
                            Select::make('unitKerjaId')
                                ->label('Unit Kerja')
                                ->options($this->unitKerjaOptions())
                                ->placeholder('Seluruh Unit Kerja')
                                ->searchable()
                                ->native(false)
                                ->live()
                                ->afterStateUpdated(function (mixed $state): void {
                                    $this->unitKerjaId = filled($state) ? (int) $state : null;
                                    $this->segarkanMonitoring();
                                })
                                ->helperText('Kosongkan untuk merangkum seluruh unit kerja sekaligus.')
                                ->columnSpan(1),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Aksi kepala halaman: mencatat capaian — satu per satu maupun massal lewat
     * berkas — lalu mengeluarkan angka yang sedang dibaca sebagai spreadsheet atau
     * laporan PDF.
     *
     * Kedua aksi pencatatan capaian dikumpulkan dalam satu grup karena keduanya
     * mengerjakan hal yang sama dengan cara berbeda, dan supaya kepala halaman tidak
     * berjejer tombol.
     *
     * Ekspor spreadsheet mengikuti cakupan unit kerja dan tahun kerja yang sedang
     * dibaca, sehingga berkasnya sama persis dengan tabel di layar; laporan PDF
     * menanyakan cakupannya lebih dahulu ({@see MemilihCakupanLaporan}). Aksinya
     * dibentuk langsung — bukan lewat exporter() yang meresolve dari container —
     * karena kelas ekspornya perlu tahu cakupan itu.
     *
     * @return array<int, Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                CatatCapaianProgramKerjaAction::make()
                    ->permission(self::PERMISSION_CATAT_CAPAIAN)
                    ->tahunKerja(fn (): ?TahunKerja => $this->tahunKerjaTerpilih())
                    ->unitKerjaOptions(fn (): array => $this->unitKerjaOptions())
                    ->defaultUnitKerja(fn (): ?int => $this->unitKerjaId)
                    ->after(fn () => $this->segarkanTampilanCapaian()),
                ImporCapaianProgramKerjaAction::make()
                    ->permission(self::PERMISSION_CATAT_CAPAIAN)
                    ->importerContext(fn (): array => $this->cakupanCapaian())
                    // Tanpa tahun kerja tidak ada program kerja yang bisa dirujuk berkas,
                    // sehingga templatenya pun akan lahir tanpa referensi.
                    ->hidden(fn (): bool => $this->tahunKerjaTerpilih() === null)
                    ->after(fn () => $this->segarkanTampilanCapaian()),
            ])
                ->label('Capaian')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('primary')
                ->button(),
            ActionGroup::make([
                ExcelExportAction::make()
                    ->permission(static::getPagePermission())
                    ->action(fn () => $this->export($this->unitKerjaId)->download()),
                PdfReportAction::make()
                    ->permission(static::getPagePermission())
                    ->cakupan($this->skemaCakupanLaporan(fn (): ?int => $this->unitKerjaId))
                    ->action(fn (array $data) => (new TabularReport(
                        $this->export($this->cakupanUnitKerja($data)),
                    ))->download()),
            ])
                ->label('Ekspor')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->button(),
        ];
    }

    /**
     * Cakupan yang boleh dicatat capaiannya lewat berkas: tahun kerja yang sedang
     * dipantau dan seluruh unit kerja yang boleh diakses pengguna — bukan sekadar unit
     * yang sedang disaring, supaya satu berkas bisa memuat beberapa unit sekaligus.
     * Nama tahun kerjanya ikut dikirim sebagai keterangan pada lembar referensi.
     *
     * @return array<string, mixed>
     */
    protected function cakupanCapaian(): array
    {
        return [
            'tahun_kerja_id' => $this->tahunKerjaTerpilih()?->getKey(),
            'nama_tahun_kerja' => $this->tahunKerjaTerpilih()?->name,
            'unit_kerja_ids' => $this->unitKerjaId !== null
                ? [$this->unitKerjaId]
                : array_keys($this->unitKerjaOptions()),
        ];
    }

    /**
     * Menyegarkan seluruh tampilan setelah capaian bertambah. Widget ringkasan &
     * grafik adalah komponen Livewire tersendiri, jadi keduanya diminta menggambar
     * ulang lewat peristiwa.
     */
    protected function segarkanTampilanCapaian(): void
    {
        $this->segarkanMonitoring();
        $this->dispatch('monitoring-diperbarui');
    }

    /**
     * @param  int|null  $unitKerjaId  Unit kerja tunggal; null berarti seluruh unit yang boleh diakses.
     */
    protected function export(?int $unitKerjaId): MonitoringProgramKerjasExport
    {
        return new MonitoringProgramKerjasExport(
            unitKerjaIds: $unitKerjaId !== null ? [$unitKerjaId] : array_keys($this->unitKerjaOptions()),
            tahunKerjaId: $this->tahunKerjaTerpilih()?->getKey(),
            namaUnitKerja: $unitKerjaId !== null ? ($this->unitKerjaOptions()[$unitKerjaId] ?? null) : null,
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?string $search, ?string $sortColumn, ?string $sortDirection, array $filters, int $page, int $recordsPerPage): LengthAwarePaginator => $this->halamanProgram($search, $sortColumn, $sortDirection, $filters, $page, $recordsPerPage))
            ->columns([
                TextColumn::make('unit_kerja')
                    ->label('Unit Kerja')
                    ->wrap()
                    ->searchable()
                    ->sortable()
                    ->description(fn (array $record): ?string => $record['bidang']),
                TextColumn::make('program')
                    ->label('Program Kerja')
                    ->wrap()
                    ->searchable()
                    ->sortable()
                    ->description(fn (array $record): ?string => $record['program_induk']),
                TextColumn::make('kategori')
                    ->label('Kategori')
                    ->badge()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('jumlah_pengajuan')
                    ->label('Pengajuan')
                    ->badge()
                    ->color(fn (array $record): string => $record['jumlah_pengajuan'] > 0 ? 'info' : 'gray')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('alokasi')
                    ->label('Anggaran Diajukan')
                    ->money('IDR')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('terserap')
                    ->label('Anggaran Terserap')
                    ->money('IDR')
                    ->alignEnd()
                    ->weight('font-semibold')
                    ->color(fn (array $record): string => $record['terserap'] > 0 ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('capaian')
                    ->label('Capaian')
                    ->badge()
                    ->alignCenter()
                    ->placeholder('Belum dilaporkan')
                    ->sortable()
                    ->formatStateUsing(fn (?float $state): ?string => $state === null ? null : number_format($state, 1, ',', '.').'%')
                    ->color(fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 80 => 'success',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    }),
            ])
            ->recordActions([
                MediaAction::make('lihatLaporanCapaian')
                    ->label('Lihat Laporan')
                    // Record dievaluasi null saat aksi dirakit di luar konteks baris.
                    ->path(fn (?array $record): array => $record === null ? [] : $this->laporanCapaian((int) $record['id'])),
            ])
            ->filters([
                SelectFilter::make('unit_kerja_id')
                    ->label('Unit Kerja')
                    ->options(fn (): array => $this->monitoring()->namaUnitKerja())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('bidang_id')
                    ->label('Bidang')
                    ->options(fn (): array => $this->bidangOptions())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('program_id')
                    ->label('Program Induk')
                    ->options(fn (): array => $this->programOptions())
                    ->searchable()
                    ->preload(),
            ])
            ->defaultPaginationPageOption(25)
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading('Belum ada program kerja')
            ->emptyStateDescription('Program kerja yang ditawarkan pada tahun kerja terpilih akan tampil di sini beserta penyerapan anggarannya.')
            ->emptyStateIcon('heroicon-o-chart-bar');
    }

    /**
     * Ringkasan cakupan terpilih untuk kartu di bawah tabel.
     */
    public function ringkasan(): RingkasanMonitoring
    {
        return $this->monitoring()->ringkasan();
    }

    /**
     * Membuang angka monitoring yang sudah ditahan lalu menggambar ulang tabelnya,
     * dipakai setelah capaian baru dicatat agar barisnya langsung ikut berubah.
     */
    public function segarkanMonitoring(): void
    {
        $this->monitoring = null;
        $this->laporanPerProgram = null;
        $this->resetTable();
    }

    /**
     * Berkas laporan seluruh realisasi tuntas sebuah program kerja, terbaru lebih
     * dahulu. Menjadi bahan pratinjau dokumen pada baris tabel.
     *
     * @return array<int, string>
     */
    protected function laporanCapaian(int $penawaranId): array
    {
        return $this->laporanPerProgram()[$penawaranId] ?? [];
    }

    /**
     * Berkas laporan tiap program kerja pada cakupan terpilih, dibaca sekali lalu
     * ditahan karena aksi pratinjau memanggilnya untuk setiap baris tabel.
     *
     * @return array<int, array<int, string>> penawaran id => path berkas
     */
    protected function laporanPerProgram(): array
    {
        if ($this->laporanPerProgram !== null) {
            return $this->laporanPerProgram;
        }

        $tahunKerjaId = $this->tahunKerjaTerpilih()?->getKey();
        $unitKerjaIds = $this->unitKerjaId !== null ? [$this->unitKerjaId] : array_keys($this->unitKerjaOptions());

        if ($tahunKerjaId === null || $unitKerjaIds === []) {
            return $this->laporanPerProgram = [];
        }

        return $this->laporanPerProgram = RealisasiProgramKerja::query()
            ->join('pengajuan_program_kerjas', 'pengajuan_program_kerjas.id', '=', 'realisasi_program_kerjas.pengajuan_program_kerja_id')
            ->join('penawaran_program_kerjas', 'penawaran_program_kerjas.id', '=', 'pengajuan_program_kerjas.penawaran_program_kerja_id')
            ->where('penawaran_program_kerjas.tahun_kerja_id', $tahunKerjaId)
            ->whereIn('pengajuan_program_kerjas.unit_kerja_id', $unitKerjaIds)
            ->where('realisasi_program_kerjas.status', EnumStatusRealisasi::Selesai->value)
            ->whereNotNull('realisasi_program_kerjas.laporan_path')
            ->orderByDesc('realisasi_program_kerjas.laporan_diserahkan_at')
            ->get([
                'realisasi_program_kerjas.laporan_path',
                'pengajuan_program_kerjas.penawaran_program_kerja_id as penawaran_id',
            ])
            ->groupBy('penawaran_id')
            ->map(fn (Collection $realisasis): array => $realisasis
                ->flatMap(fn (RealisasiProgramKerja $realisasi): array => (array) $realisasi->laporan_path)
                ->filter(fn (mixed $path): bool => is_string($path) && filled($path))
                ->unique()
                ->values()
                ->all())
            ->all();
    }

    /**
     * Satu halaman rincian program kerja, sudah disaring, diurutkan, dan dipenggal
     * sesuai keadaan tabel. Penyaringan, sortir, dan pencariannya dikerjakan di sini
     * karena barisnya dirakit di PHP, bukan berasal dari satu kueri tunggal.
     *
     * @param  array<string, array<string, mixed>>  $filters  keadaan penyaring tabel
     */
    protected function halamanProgram(?string $search, ?string $sortColumn, ?string $sortDirection, array $filters, int $page, int $recordsPerPage): LengthAwarePaginator
    {
        $baris = $this->monitoring()->barisProgram();

        foreach (['unit_kerja_id', 'bidang_id', 'program_id'] as $penyaring) {
            $nilai = $filters[$penyaring]['value'] ?? null;

            if (blank($nilai)) {
                continue;
            }

            $baris = $baris->where($penyaring, (int) $nilai);
        }

        if (filled($search)) {
            $kunci = Str::lower($search);

            $baris = $baris->filter(fn (array $record): bool => str_contains(Str::lower($record['program'].' '.$record['unit_kerja']), $kunci));
        }

        if (filled($sortColumn)) {
            $baris = $baris->sortBy($sortColumn, SORT_REGULAR, $sortDirection === 'desc');
        }

        return new LengthAwarePaginator(
            items: $baris->forPage($page, $recordsPerPage),
            total: $baris->count(),
            perPage: $recordsPerPage,
            currentPage: $page,
        );
    }

    /**
     * Angka monitoring cakupan terpilih, ditahan agar kartu ringkasan dan tabel tidak
     * menghitungnya dua kali dalam satu render.
     */
    protected function monitoring(): MonitoringAnggaran
    {
        return $this->monitoring ??= MonitoringAnggaran::untukUnits(
            $this->unitKerjaId !== null ? [$this->unitKerjaId] : array_keys($this->unitKerjaOptions()),
            $this->tahunKerjaTerpilih(),
        );
    }

    protected function tahunKerjaTerpilih(): ?TahunKerja
    {
        if ($this->tahunKerjaId === null) {
            return null;
        }

        return $this->tahunKerja ??= TahunKerja::find($this->tahunKerjaId);
    }

    /**
     * Tahun kerja yang boleh dipilih, terbaru lebih dahulu.
     *
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
     * Bidang yang boleh dipilih sebagai penyaring tabel.
     *
     * @return array<int, string>
     */
    protected function bidangOptions(): array
    {
        return Bidang::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Program induk yang boleh dipilih sebagai penyaring tabel.
     *
     * @return array<int, string>
     */
    protected function programOptions(): array
    {
        return Program::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Unit kerja yang boleh dibaca, mengikuti scope data yang dimiliki pengguna.
     *
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
