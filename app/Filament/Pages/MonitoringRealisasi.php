<?php

namespace App\Filament\Pages;

use App\Enums\EnumStatusAnggaran;
use App\Enums\EnumStatusRealisasi;
use App\Exports\MonitoringRealisasisExport;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\MediaAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Pages\Concerns\HasFilterAboveWidgets;
use App\Filament\Pages\Concerns\HasPageAuthorization;
use App\Filament\Pages\Concerns\MemilihCakupanLaporan;
use App\Filament\Pages\Widgets\AnggaranRealisasiChart;
use App\Filament\Pages\Widgets\MonitoringRealisasiOverview;
use App\Filament\Pages\Widgets\RealisasiUnitKerjaChart;
use App\Filament\Pages\Widgets\SebaranStatusRealisasiChart;
use App\Models\RealisasiProgramKerja;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Reports\TabularReport;
use App\Services\KonteksProgramKerja;
use App\Services\MonitoringRealisasi as MonitoringRealisasiService;
use App\Services\PermissionRegistrar;
use App\Services\RingkasanRealisasi;
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
use UnitEnum;

/**
 * Pemantauan realisasi program kerja: berapa kegiatan yang masih berjalan, berapa yang
 * sudah tuntas, ke mana anggarannya mengalir, dan bagaimana rincian tiap realisasinya
 * — lengkap sampai dokumen proposal dan laporannya.
 *
 * Berbeda dengan menu Pelaksanaan yang selalu berbicara tentang tahun kerja berjalan
 * ({@see KonteksProgramKerja}), halaman ini sengaja tidak terikat konteks: penyaring
 * tahun kerjanya boleh dikosongkan sehingga realisasi tahun-tahun sebelumnya tetap
 * dapat ditelusuri beserta dokumennya, walaupun tahun tersebut sudah ditutup.
 *
 * Anggaran dibaca dengan definisi yang sama dengan Buku Anggaran dan
 * {@see MonitoringProgramKerja} — berbasis kas, terhitung sejak anggaran benar-benar
 * dicairkan — lalu disandingkan dengan realisasi akhir yang dilaporkan unit kerja.
 *
 * Cakupan datanya mengikuti pembatasan data pengguna: pimpinan unit hanya melihat
 * unit kerja yang menjadi wewenangnya, sedangkan pemegang `bypass_data_scope` melihat
 * seluruh unit. Rincian satu realisasi dibuka di {@see DetailMonitoringRealisasi}.
 *
 * @property-read Schema $form
 */
class MonitoringRealisasi extends Page implements HasTable
{
    use HasFilterAboveWidgets;
    use HasPageAuthorization;
    use InteractsWithTable;
    use MemilihCakupanLaporan;

    protected string $view = 'filament.pages.monitoring-realisasi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Monitoring Realisasi';

    protected static ?string $title = 'Monitoring Realisasi Program Kerja';

    protected static ?string $slug = 'monitoring-realisasi';

    protected static ?int $navigationSort = 2;

    /**
     * Metadata untuk form Role & Hak Akses (dibaca PermissionRegistrar).
     *
     * @var array<string, mixed>
     */
    public static array $permissions = [
        'heading' => 'Monitoring Realisasi',
        'description' => 'Hak akses untuk memantau pelaksanaan realisasi program kerja beserta anggaran dan dokumennya, termasuk tahun kerja yang sudah lewat.',
        'permission_descriptions' => [
            'view_page_monitoring_realisasi' => 'Membuka pemantauan realisasi program kerja, membaca rinciannya, dan melihat dokumen proposal maupun laporannya.',
        ],
    ];

    /**
     * Penyaring tampilan halaman.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Tahun kerja yang dipantau. Bawaannya tahun kerja berjalan; null berarti seluruh
     * tahun kerja, termasuk yang sudah ditutup.
     */
    public ?int $tahunKerjaId = null;

    /**
     * Unit kerja yang dipantau. Null berarti seluruh unit yang boleh diakses.
     */
    public ?int $unitKerjaId = null;

    private ?MonitoringRealisasiService $monitoring = null;

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
        $unitKerja = $this->unitKerjaId === null
            ? 'seluruh unit kerja'
            : ($this->unitKerjaOptions()[$this->unitKerjaId] ?? 'unit kerja terpilih');

        $tahunKerja = $this->tahunKerjaId === null
            ? 'seluruh tahun kerja'
            : ($this->tahunKerjaOptions()[$this->tahunKerjaId] ?? 'tahun kerja terpilih');

        return 'Pelaksanaan realisasi program kerja '.$unitKerja.' pada '.$tahunKerja
            .'. Kosongkan penyaring tahun kerja untuk menelusuri realisasi tahun-tahun sebelumnya beserta dokumennya.';
    }

    /**
     * @return array<int, class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            MonitoringRealisasiOverview::class,
            AnggaranRealisasiChart::class,
            SebaranStatusRealisasiChart::class,
            RealisasiUnitKerjaChart::class,
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
                    ->description('Pilih cakupan yang dipantau. Kosongkan tahun kerja untuk merangkum seluruh tahun, atau unit kerja untuk merangkum seluruh unit yang boleh diakses.')
                    ->icon(Heroicon::OutlinedFunnel)
                    ->collapsible()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('tahunKerjaId')
                                ->label('Tahun Kerja')
                                ->options($this->tahunKerjaOptions())
                                ->placeholder('Seluruh Tahun Kerja')
                                ->searchable()
                                ->native(false)
                                ->live()
                                ->afterStateUpdated(function (mixed $state): void {
                                    $this->tahunKerjaId = filled($state) ? (int) $state : null;
                                    $this->monitoring = null;
                                    $this->resetTable();
                                })
                                ->helperText('Bawaannya tahun kerja berjalan; kosongkan untuk menelusuri realisasi tahun-tahun sebelumnya.')
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
                                    $this->monitoring = null;
                                    $this->resetTable();
                                })
                                ->helperText('Kosongkan untuk merangkum seluruh unit kerja sekaligus.')
                                ->columnSpan(1),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Ekspor spreadsheet mengikuti cakupan unit kerja dan tahun kerja yang sedang
     * dibaca, sehingga berkasnya sama persis dengan tabel di layar; laporan PDF
     * menanyakan cakupannya lebih dahulu ({@see MemilihCakupanLaporan}). Aksinya
     * dibentuk langsung — bukan lewat exporter() yang meresolve dari container —
     * karena kelas ekspornya perlu tahu cakupan itu.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ExcelExportAction::make()
                ->permission(static::getPagePermission())
                ->action(fn () => $this->export($this->unitKerjaId)->download()),
            PdfReportAction::make()
                ->permission(static::getPagePermission())
                ->cakupan($this->skemaCakupanLaporan(fn (): ?int => $this->unitKerjaId))
                ->action(fn (array $data) => (new TabularReport(
                    $this->export($this->cakupanUnitKerja($data)),
                ))->download()),
        ];
    }

    /**
     * @param  int|null  $unitKerjaId  Unit kerja tunggal; null berarti seluruh unit yang boleh diakses.
     */
    protected function export(?int $unitKerjaId): MonitoringRealisasisExport
    {
        return new MonitoringRealisasisExport(
            unitKerjaIds: $unitKerjaId !== null ? [$unitKerjaId] : array_keys($this->unitKerjaOptions()),
            tahunKerjaId: $this->tahunKerjaId,
            namaUnitKerja: $unitKerjaId !== null ? ($this->unitKerjaOptions()[$unitKerjaId] ?? null) : null,
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->monitoring()->queryTabel())
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (RealisasiProgramKerja $record): string => DetailMonitoringRealisasi::getUrl(['record' => $record->getRouteKey()]))
            ->columns([
                TextColumn::make('pengajuanProgramKerja.penawaranProgramKerja.tahunKerja.name')
                    ->label('Tahun Kerja')
                    ->badge()
                    ->color(fn (RealisasiProgramKerja $record): string => $record->tahunKerja()?->status?->getColor() ?? 'gray')
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('Kegiatan')
                    ->wrap()
                    ->searchable()
                    ->description(fn (RealisasiProgramKerja $record): ?string => $record->pengajuanProgramKerja?->penawaranProgramKerja?->name),
                TextColumn::make('pengajuanProgramKerja.unitKerja.name')
                    ->label('Unit Kerja')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('nominal_disetujui')
                    ->label('Anggaran Disetujui')
                    ->money('IDR')
                    ->placeholder('Belum disetujui')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('dicairkan_at')
                    ->label('Dicairkan')
                    ->date('d F Y')
                    ->placeholder('Belum cair')
                    ->sortable(),
                TextColumn::make('anggaran_digunakan')
                    ->label('Realisasi Akhir')
                    ->money('IDR')
                    ->placeholder('Belum dilaporkan')
                    ->alignEnd()
                    ->sortable()
                    ->description(fn (RealisasiProgramKerja $record): ?string => $record->sudahAdaLaporan()
                        ? $record->status_anggaran?->getLabel()
                        : null),
                TextColumn::make('persentase_ketercapaian')
                    ->label('Ketercapaian')
                    ->badge()
                    ->alignCenter()
                    ->placeholder('Belum dilaporkan')
                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : "{$state}%")
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 80 => 'success',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (RealisasiProgramKerja $record): string => $record->labelStatus())
                    ->color(fn (RealisasiProgramKerja $record): string => $record->status->getColor()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(EnumStatusRealisasi::class),
                SelectFilter::make('status_anggaran')
                    ->label('Status Anggaran')
                    ->options(EnumStatusAnggaran::class),
            ])
            ->recordActions([
                Action::make('detail')
                    ->label('Lihat Detail')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (RealisasiProgramKerja $record): string => DetailMonitoringRealisasi::getUrl(['record' => $record->getRouteKey()])),
                ActionGroup::make([
                    MediaAction::make('lihatProposal')
                        ->label('Lihat Proposal')
                        ->path('proposal_path'),
                    MediaAction::make('lihatLaporan')
                        ->label('Lihat Laporan')
                        ->path('laporan_path'),
                ]),
            ])
            ->defaultPaginationPageOption(25)
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading('Belum ada realisasi')
            ->emptyStateDescription('Realisasi program kerja yang sudah diajukan unit kerja akan tampil di sini beserta anggaran dan dokumennya.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }

    /**
     * Ringkasan cakupan terpilih untuk kartu di bawah tabel.
     */
    public function ringkasan(): RingkasanRealisasi
    {
        return $this->monitoring()->ringkasan();
    }

    /**
     * Angka pemantauan cakupan terpilih, ditahan agar kartu ringkasan dan tabel tidak
     * menghitungnya dua kali dalam satu render.
     */
    protected function monitoring(): MonitoringRealisasiService
    {
        return $this->monitoring ??= MonitoringRealisasiService::untukUnits(
            $this->unitKerjaId !== null ? [$this->unitKerjaId] : array_keys($this->unitKerjaOptions()),
            $this->tahunKerjaId,
        );
    }

    /**
     * Tahun kerja yang boleh dipilih, terbaru lebih dahulu. Seluruh tahun kerja ikut
     * ditawarkan — termasuk yang sudah ditutup maupun dikunci — karena justru itulah
     * yang membuat riwayat realisasi tahun lalu tetap terbaca.
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
     * Unit kerja yang boleh dibaca, mengikuti scope data yang dimiliki pengguna.
     *
     * @return array<int, string>
     */
    protected function unitKerjaOptions(): array
    {
        $query = UnitKerja::query()->where('is_active', true)->orderBy('name');

        $user = auth()->user();

        if ($user !== null && ! $user->canViewAllUnitData(static::getPagePermission())) {
            $query->whereIn('id', PermissionRegistrar::permittedUnitIds($user)->all());
        }

        return $query->pluck('name', 'id')->all();
    }
}
