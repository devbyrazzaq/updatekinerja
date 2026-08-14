<?php

namespace App\Filament\Pages;

use App\Enums\EnumJenisMutasiAnggaran;
use App\Exports\BukuAnggaranExport;
use App\Filament\Actions\ExcelExportAction;
use App\Filament\Actions\PdfReportAction;
use App\Filament\Pages\Concerns\HasPageAuthorization;
use App\Filament\Pages\Widgets\BukuAnggaranOverview;
use App\Filament\Pages\Widgets\MutasiAnggaranChart;
use App\Models\TahunKerja;
use App\Models\UnitKerja;
use App\Reports\TabularReport;
use App\Services\BukuAnggaran as BukuAnggaranService;
use App\Services\KonteksProgramKerja;
use App\Services\MutasiAnggaran;
use App\Services\PermissionRegistrar;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
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
 * Buku Anggaran satu unit kerja pada satu tahun kerja: mutasi anggaran urut waktu
 * beserta saldo berjalannya, dirakit oleh {@see BukuAnggaranService} dari data yang
 * sudah ada (tanpa tabel jurnal tersendiri).
 *
 * Unit kerja dan tahun kerja dipilih lewat penyaring di halaman; tahun kerja aktif
 * menjadi pilihan bawaannya. Widget di atas tabel merangkum tahun kerja tersebut
 * beserta realisasi yang sudah dilaksanakan dan menggambarkan mutasinya per bulan.
 *
 * Untuk gambaran lintas unit kerja, lihat {@see BukuAnggaranKeseluruhan}.
 *
 * @property-read Schema $form
 */
class BukuAnggaran extends Page implements HasTable
{
    use HasPageAuthorization;
    use InteractsWithTable;

    protected string $view = 'filament.pages.buku-anggaran';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Buku Anggaran Unit';

    protected static ?string $title = 'Buku Anggaran Unit';

    protected static ?string $slug = 'buku-anggaran';

    protected static ?int $navigationSort = 3;

    /**
     * Metadata untuk form Role & Hak Akses (dibaca PermissionRegistrar).
     *
     * @var array<string, mixed>
     */
    public static array $permissions = [
        'heading' => 'Buku Anggaran Unit',
        'description' => 'Hak akses untuk melihat mutasi anggaran satu unit kerja beserta saldo berjalannya.',
        'permission_descriptions' => [
            'view_page_buku_anggaran' => 'Membuka buku anggaran unit kerja dan mengekspornya ke spreadsheet.',
        ],
    ];

    /**
     * Unit kerja yang bukunya sedang dibuka. Buku selalu menampilkan satu unit kerja
     * karena saldonya melekat pada unit tersebut.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public ?int $unitKerjaId = null;

    /**
     * Tahun kerja yang bukunya sedang dibaca. Bawaannya tahun kerja aktif, tetapi tahun
     * lain boleh dipilih agar buku tahun sebelumnya tetap terbaca.
     */
    public ?int $tahunKerjaId = null;

    /**
     * Tahun kerja terpilih yang sudah dimuat, ditahan agar tidak dibaca berulang dalam
     * satu render (subheading, kartu ringkasan, tabel, dan ekspor membacanya).
     */
    private ?TahunKerja $tahunKerja = null;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Anggaran';
    }

    public function mount(): void
    {
        $this->unitKerjaId = $this->unitKerjaBawaan();
        $this->tahunKerjaId = KonteksProgramKerja::tahunBerjalan()?->getKey();

        $this->form->fill([
            'unitKerjaId' => $this->unitKerjaId,
            'tahunKerjaId' => $this->tahunKerjaId,
        ]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        $tahunKerja = $this->tahunKerjaTerpilih();

        if ($tahunKerja === null) {
            return 'Tahun kerja aktif belum ditetapkan. Pilih konteks di Pengaturan Program Kerja agar buku dapat disusun.';
        }

        return 'Mutasi anggaran unit kerja pada '.$tahunKerja->name.'. Kredit menambah anggaran yang bisa dipakai, debit menguranginya; pemasukan tercatat terpisah karena belum menambah plafon.';
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
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            'unitKerjaId' => $this->unitKerjaId,
            'tahunKerjaId' => $this->tahunKerjaId,
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // Exporter perlu tahu unit kerja yang sedang dibuka, sehingga aksinya
            // dibentuk langsung alih-alih lewat exporter() yang meresolve dari container.
            ExcelExportAction::make()
                ->permission(static::getPagePermission())
                ->visible(fn (): bool => $this->unitKerjaId !== null)
                ->action(fn () => (new BukuAnggaranExport(
                    unitKerjaId: $this->unitKerjaId,
                    tahunKerjaId: $this->tahunKerjaId,
                ))->download()),
            PdfReportAction::make()
                ->permission(static::getPagePermission())
                ->visible(fn (): bool => $this->unitKerjaId !== null)
                ->action(fn () => (new TabularReport(new BukuAnggaranExport(
                    unitKerjaId: $this->unitKerjaId,
                    tahunKerjaId: $this->tahunKerjaId,
                )))->download()),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tampilkan Data')
                    ->description('Buku anggaran disusun per unit kerja pada satu tahun kerja.')
                    ->icon(Heroicon::OutlinedFunnel)
                    ->collapsible()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('unitKerjaId')
                                ->label('Unit Kerja')
                                ->options($this->unitKerjaOptions())
                                ->searchable()
                                ->native(false)
                                ->selectablePlaceholder(false)
                                ->live()
                                ->afterStateUpdated(function (mixed $state): void {
                                    $this->unitKerjaId = filled($state) ? (int) $state : null;
                                    $this->resetTable();
                                })
                                ->columnSpan(1),
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
                                    $this->resetTable();
                                })
                                ->helperText('Bawaannya tahun kerja aktif; pilih tahun lain untuk membaca buku tahun sebelumnya.')
                                ->columnSpan(1),
                        ]),
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
                    ->label('Saldo')
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
            ->emptyStateIcon('heroicon-o-book-open');
    }

    /**
     * Ringkasan buku untuk kartu di atas tabel.
     *
     * @return array{kredit: float, debit: float, pemasukan: float, saldo: float, pagu: float}
     */
    public function ringkasan(): array
    {
        return $this->buku()?->ringkasan()
            ?? ['kredit' => 0.0, 'debit' => 0.0, 'pemasukan' => 0.0, 'saldo' => 0.0, 'pagu' => 0.0];
    }

    /**
     * Baris buku dalam bentuk array, dikunci agar stabil antar render Livewire.
     *
     * @return Collection<string, array<string, mixed>>
     */
    protected function barisBuku(): Collection
    {
        $buku = $this->buku();

        if ($buku === null) {
            return collect();
        }

        return $buku->mutasi()->mapWithKeys(
            fn (MutasiAnggaran $mutasi): array => [$mutasi->kunci() => $mutasi->toArray()],
        );
    }

    protected function buku(): ?BukuAnggaranService
    {
        $tahunKerja = $this->tahunKerjaTerpilih();

        if ($this->unitKerjaId === null || $tahunKerja === null) {
            return null;
        }

        return BukuAnggaranService::untukUnit($this->unitKerjaId, $tahunKerja);
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
     * Unit kerja terpilih saat halaman dibuka: unit kerja pengguna bila termasuk yang
     * boleh diakses, selain itu unit pertama yang boleh diakses.
     */
    protected function unitKerjaBawaan(): ?int
    {
        $opsi = $this->unitKerjaOptions();
        $milikPengguna = auth()->user()?->unit_kerja_id;

        if ($milikPengguna !== null && array_key_exists($milikPengguna, $opsi)) {
            return (int) $milikPengguna;
        }

        $pertama = array_key_first($opsi);

        return $pertama !== null ? (int) $pertama : null;
    }

    /**
     * Unit kerja yang boleh dipilih pengguna, mengikuti scope data yang dimiliki.
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
