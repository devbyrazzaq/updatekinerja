<?php

namespace App\Filament\Pages;

use App\Exports\RingkasanUnitKerjasExport;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Pages\Concerns\HasPageAuthorization;
use App\Filament\Pages\Widgets\PenyerapanUnitKerjaChart;
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
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Rekap satu tahun kerja untuk seluruh unit kerja sekaligus: cukup pilih tahunnya,
 * lalu terlihat dari total pagu berapa persen yang terserap tiap unit dan berapa
 * capaian program kerjanya.
 *
 * Halaman ini menjawab pertanyaan "unit mana yang tertinggal" dalam satu layar;
 * untuk menelusuri satu unit sampai ke tiap program kerjanya, lihat
 * {@see MonitoringProgramKerja}, dan untuk menyandingkan beberapa unit atau beberapa
 * tahun secara sengaja, lihat {@see PerbandinganMonitoring}.
 *
 * @property-read Schema $form
 */
class RingkasanUnitKerja extends Page implements HasTable
{
    use HasPageAuthorization;
    use InteractsWithTable;

    protected string $view = 'filament.pages.ringkasan-unit-kerja';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Ringkasan Unit Kerja';

    protected static ?string $title = 'Ringkasan Unit Kerja';

    protected static ?string $slug = 'ringkasan-unit-kerja';

    protected static ?int $navigationSort = 3;

    /**
     * Metadata untuk form Role & Hak Akses (dibaca PermissionRegistrar).
     *
     * @var array<string, mixed>
     */
    public static array $permissions = [
        'heading' => 'Ringkasan Unit Kerja',
        'description' => 'Hak akses untuk melihat rekap pagu, penyerapan anggaran, dan capaian seluruh unit kerja pada satu tahun kerja.',
        'permission_descriptions' => [
            'view_page_ringkasan_unit_kerja' => 'Membuka rekap penyerapan anggaran seluruh unit kerja per tahun kerja.',
        ],
    ];

    /**
     * Penyaring tampilan halaman.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Tahun kerja yang direkap. Bawaannya tahun kerja berjalan.
     */
    public ?int $tahunKerjaId = null;

    private ?MonitoringAnggaran $monitoring = null;

    private ?TahunKerja $tahunKerja = null;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Monitoring';
    }

    public function mount(): void
    {
        $this->tahunKerjaId = KonteksProgramKerja::tahunBerjalan()?->getKey();

        $this->form->fill(['tahunKerjaId' => $this->tahunKerjaId]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        $tahunKerja = $this->tahunKerjaTerpilih();

        if ($tahunKerja === null) {
            return 'Tahun kerja belum ditetapkan. Pilih tahun kerja pada penyaring untuk menyusun rekap.';
        }

        return 'Pagu, penyerapan anggaran, dan capaian program kerja seluruh unit kerja pada '.$tahunKerja->name.'.';
    }

    /**
     * @return array<int, class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            PenyerapanUnitKerjaChart::class,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            'unitKerjaId' => null,
            'tahunKerjaId' => $this->tahunKerjaId,
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tampilkan Data')
                    ->description('Rekap disusun dari seluruh unit kerja yang boleh diakses pada satu tahun kerja.')
                    ->icon(Heroicon::OutlinedFunnel)
                    ->collapsible()
                    ->schema([
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
                                $this->monitoring = null;
                                $this->resetTable();
                            })
                            ->helperText('Bawaannya tahun kerja berjalan; pilih tahun lain untuk merekap tahun sebelumnya.'),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Ekspor mengikuti cakupan yang sedang dibaca, sehingga berkasnya sama persis
     * dengan tabel di layar. Aksinya dibentuk langsung — bukan lewat exporter() yang
     * meresolve dari container — karena kelas ekspornya perlu tahu cakupan itu.
     *
     * Berbeda dengan halaman monitoring lain, laporan PDF di sini sengaja tidak
     * menanyakan cakupan unit kerja. Isi halaman ini adalah perbandingan seluruh unit
     * berdampingan — menyaringnya ke satu unit hanya menyisakan satu baris dan
     * menghapus maknanya — sehingga laporannya selalu memuat semua unit yang boleh
     * diakses.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ExcelExportAction::make()
                ->permission(static::getPagePermission())
                ->action(fn () => $this->export()->download()),
            PdfReportAction::make()
                ->permission(static::getPagePermission())
                ->action(fn () => (new TabularReport($this->export()))->download()),
        ];
    }

    protected function export(): RingkasanUnitKerjasExport
    {
        return new RingkasanUnitKerjasExport(
            unitKerjaIds: array_keys($this->unitKerjaOptions()),
            tahunKerjaId: $this->tahunKerjaId,
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => $this->barisUnitKerja())
            ->columns([
                TextColumn::make('label')
                    ->label('Unit Kerja')
                    ->wrap()
                    ->description(fn (array $record): string => $record['jumlah_program_diajukan'].' dari '.$record['jumlah_program'].' program kerja dilaksanakan'),
                TextColumn::make('pagu')
                    ->label('Pagu Anggaran')
                    ->money('IDR')
                    ->alignEnd(),
                TextColumn::make('terserap')
                    ->label('Terserap')
                    ->money('IDR')
                    ->alignEnd()
                    ->color('success')
                    ->description(fn (array $record): ?string => $record['komitmen'] > 0
                        ? 'Menunggu cair '.$this->rupiah($record['komitmen'])
                        : null),
                TextColumn::make('sisa_pagu')
                    ->label('Sisa Pagu')
                    ->money('IDR')
                    ->alignEnd()
                    ->color(fn (array $record): string => $record['sisa_pagu'] < 0 ? 'danger' : 'gray'),
                TextColumn::make('persentase_penyerapan')
                    ->label('Penyerapan')
                    ->badge()
                    ->alignCenter()
                    ->placeholder('Belum berpagu')
                    ->formatStateUsing(fn (?float $state): ?string => $state === null ? null : number_format($state, 1, ',', '.').'%')
                    ->color(fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state > 100 => 'danger',
                        $state >= 75 => 'success',
                        $state >= 40 => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('jumlah_realisasi')
                    ->label('Realisasi')
                    ->alignCenter()
                    ->formatStateUsing(fn (int $state, array $record): string => $record['jumlah_selesai'].' / '.$state)
                    ->description('Selesai / berjalan'),
                TextColumn::make('capaian')
                    ->label('Capaian')
                    ->badge()
                    ->alignCenter()
                    ->placeholder('Belum dilaporkan')
                    ->formatStateUsing(fn (?float $state): ?string => $state === null ? null : number_format($state, 1, ',', '.').'%')
                    ->color(fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 80 => 'success',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    }),
            ])
            ->paginated(false)
            ->emptyStateHeading('Belum ada unit kerja yang dapat direkap')
            ->emptyStateDescription('Rekap tersusun setelah tahun kerja dipilih dan unit kerja memiliki pagu atau program kerja.')
            ->emptyStateIcon('heroicon-o-squares-2x2');
    }

    /**
     * Angka gabungan seluruh unit kerja untuk kartu di atas tabel.
     */
    public function ringkasan(): RingkasanMonitoring
    {
        return $this->monitoring()->ringkasan();
    }

    /**
     * Baris rekap per unit kerja, dikunci pada id unitnya agar stabil antar render.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function barisUnitKerja(): Collection
    {
        return $this->monitoring()
            ->perUnitKerja()
            ->mapWithKeys(fn (RingkasanMonitoring $ringkasan): array => [
                $ringkasan->unitKerjaId => $ringkasan->toArray(),
            ]);
    }

    protected function rupiah(float $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }

    /**
     * Angka rekap, ditahan agar kartu ringkasan, grafik, dan tabel tidak menghitungnya
     * berulang dalam satu render.
     */
    protected function monitoring(): MonitoringAnggaran
    {
        return $this->monitoring ??= MonitoringAnggaran::untukUnits(
            array_keys($this->unitKerjaOptions()),
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
     * Unit kerja yang direkap, mengikuti scope data yang dimiliki pengguna.
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
