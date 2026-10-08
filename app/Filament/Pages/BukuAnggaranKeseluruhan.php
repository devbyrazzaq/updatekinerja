<?php

namespace App\Filament\Pages;

use App\Enums\EnumJenisMutasiAnggaran;
use App\Exports\BukuAnggaranExport;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Pages\Concerns\HasPageAuthorization;
use App\Filament\Pages\Concerns\MemilihCakupanLaporan;
use App\Filament\Pages\Widgets\BukuAnggaranOverview;
use App\Filament\Pages\Widgets\MutasiAnggaranChart;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Reports\TabularReport;
use App\Services\BukuAnggaranGabungan;
use App\Services\KonteksProgramKerja;
use App\Services\MutasiAnggaran;
use App\Services\PermissionRegistrar;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Buku Anggaran keseluruhan: mutasi anggaran seluruh unit kerja yang boleh diakses
 * pengguna dalam satu buku, dirakit oleh {@see BukuAnggaranGabungan}.
 *
 * Saldonya adalah sisa total pagu anggaran — pagu semua unit kerja menjadi saldo
 * pembuka, lalu berkurang setiap kali anggaran dicairkan. Untuk saldo satu unit kerja,
 * lihat {@see BukuAnggaran}.
 *
 * Tahun kerja dipilih lewat penyaring di halaman, bawaannya tahun kerja aktif. Widget di
 * atas tabel merangkum tahun kerja tersebut beserta realisasi seluruh unit kerja dan
 * menggambarkan mutasinya per bulan.
 *
 * @property-read Schema $form
 */
class BukuAnggaranKeseluruhan extends Page implements HasTable
{
    use HasPageAuthorization;
    use InteractsWithTable;
    use MemilihCakupanLaporan;

    protected string $view = 'filament.pages.buku-anggaran-keseluruhan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Buku Anggaran Keseluruhan';

    protected static ?string $title = 'Buku Anggaran Keseluruhan';

    protected static ?string $slug = 'buku-anggaran-keseluruhan';

    protected static ?int $navigationSort = 4;

    /**
     * Metadata untuk form Role & Hak Akses (dibaca PermissionRegistrar).
     *
     * @var array<string, mixed>
     */
    public static array $permissions = [
        'heading' => 'Buku Anggaran Keseluruhan',
        'description' => 'Hak akses untuk melihat mutasi anggaran seluruh unit kerja beserta sisa total pagu anggaran.',
        'permission_descriptions' => [
            'view_page_buku_anggaran_keseluruhan' => 'Membuka buku anggaran seluruh unit kerja dan mengekspornya ke spreadsheet.',
        ],
    ];

    /**
     * Penyaring tampilan halaman.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Tahun kerja yang bukunya sedang dibaca. Bawaannya tahun kerja aktif, tetapi tahun
     * lain boleh dipilih agar buku tahun sebelumnya tetap terbaca.
     */
    public ?int $tahunKerjaId = null;

    private ?BukuAnggaranGabungan $buku = null;

    /**
     * Tahun kerja terpilih yang sudah dimuat, ditahan agar tidak dibaca berulang dalam
     * satu render (subheading, kartu ringkasan, tabel, dan ekspor membacanya).
     */
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
            return 'Tahun kerja aktif belum ditetapkan. Pilih konteks di Pengaturan Program Kerja agar buku dapat disusun.';
        }

        return 'Mutasi anggaran seluruh unit kerja pada '.$tahunKerja->name.'. Saldo dimulai dari total pagu anggaran dan berkurang setiap anggaran dicairkan; pemasukan tercatat terpisah karena belum menambah plafon.';
    }

    /**
     * @return array<int, class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            BukuAnggaranOverview::class,
            MutasiAnggaranChart::class,
        ];
    }

    /**
     * Tanpa unit kerja tertentu, widget merangkum seluruh unit yang boleh diakses
     * pengguna — sejalan dengan isi bukunya.
     *
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            'unitKerjaId' => null,
            'tahunKerjaId' => $this->tahunKerjaId,
            'permissionLingkup' => static::getPagePermission(),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // Exporter perlu tahu unit kerja yang sedang dibaca, sehingga aksinya
            // dibentuk langsung alih-alih lewat exporter() yang meresolve dari container.
            ExcelExportAction::make()
                ->permission(static::getPagePermission())
                ->action(fn () => $this->export(null)->download()),
            PdfReportAction::make()
                ->permission(static::getPagePermission())
                ->cakupan($this->skemaCakupanLaporan())
                ->action(fn (array $data) => (new TabularReport(
                    $this->export($this->cakupanUnitKerja($data)),
                ))->download()),
        ];
    }

    /**
     * Buku gabungan seluruh unit kerja — isi halaman ini — atau buku satu unit kerja
     * bila laporannya dipersempit lewat modal cakupan.
     *
     * @param  int|null  $unitKerjaId  Unit kerja tunggal; null berarti buku gabungan.
     */
    protected function export(?int $unitKerjaId): BukuAnggaranExport
    {
        return new BukuAnggaranExport(
            unitKerjaId: $unitKerjaId,
            unitKerja: $unitKerjaId === null ? $this->unitKerjaOptions() : [],
            tahunKerjaId: $this->tahunKerjaId,
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tampilkan Data')
                    ->description('Buku anggaran keseluruhan disusun dari seluruh unit kerja yang boleh diakses pada satu tahun kerja.')
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
                                $this->buku = null;
                                $this->resetTable();
                            })
                            ->helperText('Bawaannya tahun kerja aktif; pilih tahun lain untuk membaca buku tahun sebelumnya.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => $this->barisBuku())
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->formatStateUsing(fn (Carbon $state): string => $state->translatedFormat('d F Y')),
                TextColumn::make('jenis')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (EnumJenisMutasiAnggaran $state): string => $state->getLabel())
                    ->color(fn (EnumJenisMutasiAnggaran $state): string => $state->getColor()),
                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->wrap()
                    ->description(fn (array $record): ?string => $record['unit_kerja'])
                    ->url(fn (array $record): ?string => $record['url'])
                    ->openUrlInNewTab(),
                TextColumn::make('debit')
                    ->label('Debit')
                    ->money('IDR')
                    ->color('danger')
                    ->alignEnd()
                    ->placeholder('-')
                    ->state(fn (array $record): ?float => $record['debit'] > 0 ? $record['debit'] : null),
                TextColumn::make('kredit')
                    ->label('Kredit')
                    ->money('IDR')
                    ->color('success')
                    ->alignEnd()
                    ->placeholder('-')
                    ->state(fn (array $record): ?float => $record['kredit'] > 0 ? $record['kredit'] : null),
                TextColumn::make('saldo')
                    ->label('Sisa Pagu Anggaran')
                    ->money('IDR')
                    ->weight('font-semibold')
                    ->alignEnd()
                    ->color(fn (array $record): string => $record['saldo'] < 0 ? 'danger' : 'gray'),
                TextColumn::make('pemasukan')
                    ->label('Pemasukan')
                    ->money('IDR')
                    ->color('info')
                    ->alignEnd()
                    ->placeholder('-')
                    ->state(fn (array $record): ?float => $record['pemasukan'] > 0 ? $record['pemasukan'] : null)
                    ->description(fn (array $record): ?string => $record['pemasukan'] > 0 ? 'Tidak mengubah saldo' : null),
            ])
            ->paginated(false)
            ->emptyStateHeading('Belum ada mutasi anggaran')
            ->emptyStateDescription('Mutasi tercatat saat pagu ditetapkan, anggaran dicairkan, selisih anggaran dituntaskan, atau pemasukan dicatat.')
            ->emptyStateIcon('heroicon-o-rectangle-stack');
    }

    /**
     * Ringkasan buku untuk kartu di atas tabel.
     *
     * @return array{kredit: float, debit: float, pemasukan: float, saldo: float, pagu: float}
     */
    public function ringkasan(): array
    {
        return $this->buku()->ringkasan();
    }

    /**
     * Baris buku dalam bentuk array, dikunci agar stabil antar render Livewire. Nama
     * unit kerja ikut disertakan agar kolomnya terisi.
     *
     * @return Collection<string, array<string, mixed>>
     */
    protected function barisBuku(): Collection
    {
        $namaUnitKerja = $this->unitKerjaOptions();

        return $this->buku()->mutasi()->mapWithKeys(
            fn (MutasiAnggaran $mutasi): array => [$mutasi->kunci() => [
                ...$mutasi->toArray(),
                'unit_kerja' => $namaUnitKerja[$mutasi->unitKerjaId] ?? '-',
            ]],
        );
    }

    /**
     * Buku gabungan dipakai kartu ringkasan dan tabel dalam satu render, jadi ditahan
     * agar tidak dirakit dua kali.
     */
    protected function buku(): BukuAnggaranGabungan
    {
        return $this->buku ??= BukuAnggaranGabungan::untukUnits(
            $this->unitKerjaOptions(),
            $this->tahunKerjaTerpilih(),
        );
    }

    /**
     * Tahun kerja yang bukunya sedang dibaca.
     */
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
     * Unit kerja yang dibaca, mengikuti scope data yang dimiliki pengguna.
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
