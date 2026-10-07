<?php

namespace App\Filament\Pages;

use App\Enums\EnumStatusRealisasi;
use App\Filament\Actions\AjukanRealisasiAction;
use App\Filament\Actions\KirimLaporanRealisasiAction;
use App\Filament\Actions\MediaAction;
use App\Filament\Actions\ProsesPencairanAction;
use App\Filament\Actions\RevisiRealisasiAction;
use App\Filament\Actions\SetujuiRealisasiAction;
use App\Filament\Actions\TandaiDicairkanAction;
use App\Filament\Actions\TolakRealisasiAction;
use App\Filament\Actions\TuntaskanSelisihAnggaranAction;
use App\Filament\Pages\Concerns\HasPageAuthorization;
use App\Filament\Resources\Concerns\HasVerificationTableFilters;
use App\Filament\Resources\RealisasiProgramKerjas\Schemas\RealisasiProgramKerjaInfolist;
use App\Filament\Resources\VerifikasiLaporanLampaus\VerifikasiLaporanLampauResource;
use App\Models\RealisasiProgramKerja;
use App\Models\TahunKerja;
use App\Services\KonteksProgramKerja;
use App\Services\PermissionRegistrar;
use App\Services\TransisiTahunKerja;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Tunggakan tahun kerja yang sudah ditinggalkan. Begitu tahun kerja berganti, seluruh
 * menu Pelaksanaan dan Verifikasi hanya berbicara tentang tahun yang sedang berjalan
 * ({@see KonteksProgramKerja}), sehingga angka anggaran tahun baru tidak tercampur
 * data tahun lalu.
 *
 * Realisasi tahun lalu yang belum tuntas tidak ikut hilang — ia dikerjakan di sini:
 * unit kerja melihat detailnya, memperbaiki proposal, dan mengunggah laporan; para
 * verifikator menyetujui/menolak proposal, Biro Keuangan menjadwalkan pencairan dan
 * menuntaskan selisih anggaran. Laporan yang sudah diunggah diverifikasi lewat menu
 * {@see VerifikasiLaporanLampauResource}, sejajar dengan Verifikasi Laporan tahun
 * berjalan. Setiap aksi tetap memeriksa hak aksesnya sendiri, sehingga satu pengguna
 * hanya melihat tombol yang memang menjadi bagiannya.
 *
 * Daftar ini persis pekerjaan yang menahan penguncian tahun kerja
 * ({@see TransisiTahunKerja::penghambatPenguncian()}): begitu kosong,
 * tahun lama boleh dikunci dari Pengaturan Program Kerja.
 */
class PenyelesaianTahunLalu extends Page implements HasTable
{
    use HasPageAuthorization;
    use HasVerificationTableFilters;
    use InteractsWithTable;

    protected string $view = 'filament.pages.penyelesaian-tahun-lalu';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxArrowDown;

    protected static ?string $navigationLabel = 'Penyelesaian Tahun Lalu';

    protected static ?string $title = 'Penyelesaian Realisasi Tahun Lalu';

    protected static ?string $slug = 'penyelesaian-tahun-lalu';

    protected static ?int $navigationSort = 7;

    /**
     * Metadata untuk form Role & Hak Akses (dibaca PermissionRegistrar).
     *
     * @var array<string, mixed>
     */
    public static array $permissions = [
        'heading' => 'Penyelesaian Tahun Lalu',
        'description' => 'Hak akses untuk menuntaskan realisasi tahun kerja yang sudah ditinggalkan namun belum dikunci.',
        'permission_descriptions' => [
            'view_page_penyelesaian_tahun_lalu' => 'Membuka daftar tunggakan realisasi tahun lalu dan menjalankan aksi penyelesaiannya sesuai peran masing-masing.',
        ],
    ];

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Pelaksanaan';
    }

    /**
     * Menu ini sengaja disembunyikan saat tidak ada tahun Penutupan: selama tahun
     * kerja berganti mulus, halaman ini tidak punya pekerjaan apa pun.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return parent::shouldRegisterNavigation() && KonteksProgramKerja::tahunPenutupanIds() !== [];
    }

    public static function getNavigationBadge(): ?string
    {
        $jumlah = static::tunggakan()->count();

        return $jumlah > 0 ? (string) $jumlah : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $tahunKerja = TahunKerja::penutupan();

        if ($tahunKerja->isEmpty()) {
            return 'Tidak ada tahun kerja yang sedang ditutup. Seluruh pekerjaan berjalan lewat menu Pelaksanaan dan Verifikasi seperti biasa.';
        }

        return 'Realisasi '.$tahunKerja->pluck('name')->implode(', ').' yang belum tuntas. '
            .'Selesaikan seluruh baris di bawah agar tahun kerja tersebut dapat dikunci dari Pengaturan Program Kerja. '
            .'Data ini sudah tidak muncul di menu Pelaksanaan maupun Verifikasi agar tidak tercampur tahun kerja yang sedang berjalan.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => static::tunggakan())
            ->defaultSort('created_at')
            ->emptyStateHeading('Tidak ada tunggakan tahun lalu')
            ->emptyStateDescription('Seluruh realisasi tahun kerja yang ditutup sudah tuntas, sehingga tahun tersebut siap dikunci.')
            ->emptyStateIcon('heroicon-o-check-badge')
            ->columns([
                TextColumn::make('pengajuanProgramKerja.penawaranProgramKerja.tahunKerja.name')
                    ->label('Tahun Kerja')
                    ->badge()
                    ->color('warning'),
                TextColumn::make('name')
                    ->label('Kegiatan')
                    ->searchable()
                    ->wrap()
                    ->description(fn (RealisasiProgramKerja $record): string => $record->keteranganTunggakan()),
                TextColumn::make('pengajuanProgramKerja.unitKerja.name')
                    ->label('Unit Kerja')
                    ->searchable(),
                TextColumn::make('nominal_disetujui')
                    ->label('Nominal Disetujui')
                    ->money('IDR')
                    ->placeholder('Belum disetujui'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (RealisasiProgramKerja $record): string => $record->labelStatus())
                    ->color(fn (RealisasiProgramKerja $record): string => $record->status->getColor()),
                TextColumn::make('status_penyelesaian_anggaran')
                    ->label('Tindak Lanjut Anggaran')
                    ->badge()
                    ->placeholder('-')
                    ->description(fn (RealisasiProgramKerja $record): ?string => $record->keteranganPenyelesaianAnggaran()),
                TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime('d F Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tahun_kerja_id')
                    ->label('Tahun Kerja')
                    ->placeholder('Semua Tahun Kerja')
                    ->options(fn (): array => TahunKerja::query()
                        ->whereIn('id', KonteksProgramKerja::tahunPenutupanIds())
                        ->orderByDesc('tahun')
                        ->pluck('name', 'id')
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, mixed $tahunKerjaId): Builder => $query->whereHas(
                            'pengajuanProgramKerja.penawaranProgramKerja',
                            fn (Builder $penawaran): Builder => $penawaran->where('tahun_kerja_id', $tahunKerjaId),
                        ),
                    )),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(EnumStatusRealisasi::class),
                static::unitKerjaFilter('pengajuanProgramKerja'),
            ])
            ->recordActions([
                // Tahap unit kerja.
                AjukanRealisasiAction::make(),
                KirimLaporanRealisasiAction::make(),
                // Tahap verifikasi proposal (Rektor & Wakil Rektor).
                SetujuiRealisasiAction::make(),
                // Tahap Biro Keuangan.
                ProsesPencairanAction::make(),
                TandaiDicairkanAction::make(),
                TuntaskanSelisihAnggaranAction::make(),
                ViewAction::make()
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (RealisasiProgramKerja $record): string => $record->name ?? 'Detail Realisasi Program Kerja')
                    ->modalWidth(Width::SevenExtraLarge)
                    ->stickyModalHeader()
                    ->modalCancelActionLabel('Tutup')
                    ->schema(fn (Schema $schema): Schema => RealisasiProgramKerjaInfolist::configure($schema)),
                ActionGroup::make([
                    RevisiRealisasiAction::make(),
                    TolakRealisasiAction::make(),
                    MediaAction::make('lihatProposal')
                        ->label('Lihat Proposal')
                        ->path('proposal_path'),
                    MediaAction::make('lihatLaporan')
                        ->label('Lihat Laporan')
                        ->path('laporan_path'),
                ]),
            ]);
    }

    /**
     * Tunggakan tahun Penutupan yang boleh dilihat pengguna ini. Halaman memakai
     * aturan pembatasan data yang sama dengan menu Realisasi Program Kerja: unit
     * kerja hanya melihat miliknya sendiri, pemegang `bypass_data_scope` melihat
     * semuanya.
     *
     * @return Builder<RealisasiProgramKerja>
     */
    protected static function tunggakan(): Builder
    {
        $query = RealisasiProgramKerja::tunggakanTahunPenutupan()
            ->with(['pengajuanProgramKerja.unitKerja', 'pengajuanProgramKerja.penawaranProgramKerja.tahunKerja']);

        $user = auth()->user();

        if ($user !== null && ! $user->isPrivileged()) {
            $unitIds = PermissionRegistrar::permittedUnitIds($user)->all();
            $query->whereHas('pengajuanProgramKerja', fn (Builder $q): Builder => $q->whereIn('unit_kerja_id', $unitIds));
        }

        return $query;
    }
}
