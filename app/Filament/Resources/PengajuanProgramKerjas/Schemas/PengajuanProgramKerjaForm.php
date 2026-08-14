<?php

namespace App\Filament\Resources\PengajuanProgramKerjas\Schemas;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\PaguAnggaran;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\RealisasiProgramKerja;
use App\Services\KonteksProgramKerja;
use App\Services\UnitKerjaAktif;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Number;

class PengajuanProgramKerjaForm
{
    /**
     * Awalan kunci memo penawaran di container. Container dibangun ulang tiap
     * permintaan, sehingga memo tidak pernah bocor antar permintaan — penting sejak
     * penawaran menentukan tahun kerja yang dipakai seluruh perhitungan anggaran
     * form ini, bukan sekadar detail tampilan.
     */
    protected const MEMO_PENAWARAN = 'pengajuan-program-kerja.penawaran.';

    /**
     * @param  EnumStatusTahunKerja  $slot  Slot tahun kerja milik menu pemanggil; program kerja
     *                                      yang boleh diajukan dibatasi ke tahun penghuni slot ini.
     */
    public static function configure(Schema $schema, EnumStatusTahunKerja $slot = EnumStatusTahunKerja::Berjalan): Schema
    {
        return $schema
            ->components([
                static::ringkasanAnggaranSection(),
                Grid::make(2)
                    ->schema([
                        static::formSection($slot),
                        static::sidebar(),
                    ])->columnSpanFull(),
            ]);
    }

    /**
     * Ringkasan anggaran unit kerja pada tahun kerja aktif: total pagu, dana yang
     * sudah dialokasikan, dan sisanya. Tampil penuh di atas dan selalu terlihat,
     * mengikuti unit kerja yang dipilih di form.
     */
    protected static function ringkasanAnggaranSection(): Section
    {
        return Section::make('Ringkasan Anggaran')
            ->description('Pagu unit kerja pada tahun kerja aktif dibandingkan dengan dana yang sudah dialokasikan.')
            ->headerActions([
                static::rincianAlokasiAction(),
            ])
            ->columnSpanFull()
            ->columns(3)
            ->schema([
                TextEntry::make('total_anggaran')
                    ->label('Total Anggaran')
                    ->state(fn (Get $get): string => static::nominalDenganPersen(static::totalAnggaran($get), static::totalAnggaran($get)))
                    ->size(TextSize::Large)
                    ->weight(FontWeight::Bold)
                    ->helperText('Pagu anggaran unit kerja pada tahun kerja aktif.'),
                TextEntry::make('dana_dialokasikan')
                    ->label('Sudah Dialokasikan')
                    ->state(fn (Get $get): string => static::nominalDenganPersen(static::danaDialokasikan($get), static::totalAnggaran($get)))
                    ->size(TextSize::Large)
                    ->weight(FontWeight::Bold)
                    ->helperText('Total seluruh pengajuan (selain ditolak) pada unit & tahun kerja ini.')
                    ->color('warning'),
                TextEntry::make('sisa_anggaran')
                    ->label('Sisa Anggaran')
                    ->state(fn (Get $get): string => static::nominalDenganPersen(static::sisaAnggaran($get), static::totalAnggaran($get)))
                    ->size(TextSize::Large)
                    ->weight(FontWeight::Bold)
                    ->helperText(fn (Get $get): ?string => static::keteranganPenyesuaian($get))
                    ->color(fn (Get $get): string => static::sisaAnggaran($get) < 0 ? 'danger' : 'success'),
            ]);
    }

    /**
     * Kolom kiri: isian pengajuan program kerja.
     */
    protected static function formSection(EnumStatusTahunKerja $slot): Section
    {
        return Section::make('Informasi Pengajuan')
            ->description('Pilih unit kerja lebih dulu, lalu program kerja yang diajukan. Isi nominal pengajuan tidak melebihi sisa anggaran. Estimasi waktu bersifat opsional, deskripsi kegiatan wajib diisi.')
            ->columnSpan(1)
            ->schema([
                Select::make('unit_kerja_id')
                    ->label('Unit Kerja')
                    ->relationship('unitKerja', 'name', fn (Builder $query): Builder => UnitKerjaAktif::batasiKueri($query))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->default(fn (): ?int => UnitKerjaAktif::id() ?? auth()->user()?->unit_kerja_id)
                    ->afterStateUpdated(static function (Get $get, Set $set): void {
                        $penawaranId = $get('penawaran_program_kerja_id');

                        if (blank($penawaranId)) {
                            return;
                        }

                        $masihSesuai = PenawaranProgramKerja::query()
                            ->whereKey($penawaranId)
                            ->where('unit_kerja_id', $get('unit_kerja_id'))
                            ->exists();

                        if (! $masihSesuai) {
                            $set('penawaran_program_kerja_id', null);
                        }
                    })
                    ->columnSpanFull(),
                Select::make('penawaran_program_kerja_id')
                    ->label('Program Kerja')
                    ->relationship(
                        'penawaranProgramKerja',
                        'name',
                        fn (Builder $query, Get $get): Builder => KonteksProgramKerja::applySlot(
                            $query->when(
                                filled($get('unit_kerja_id')),
                                fn (Builder $subQuery): Builder => $subQuery->where('unit_kerja_id', $get('unit_kerja_id')),
                            ),
                            $slot,
                        ),
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->disabled(fn (Get $get, string $operation): bool => $operation === 'edit' || blank($get('unit_kerja_id')))
                    ->helperText('Hanya menampilkan program kerja pada unit kerja yang dipilih.')
                    ->rule(static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        if (blank($value)) {
                            return;
                        }

                        $sesuai = PenawaranProgramKerja::query()
                            ->whereKey($value)
                            ->where('unit_kerja_id', $get('unit_kerja_id'))
                            ->exists();

                        if (! $sesuai) {
                            $fail('Program kerja tidak sesuai dengan unit kerja yang dipilih.');
                        }
                    })
                    ->columnSpanFull(),
                MoneyInput::make('alokasi_anggaran')
                    ->label('Pengajuan Anggaran')
                    ->required()
                    ->helperText(fn (Get $get, ?PengajuanProgramKerja $record): string => 'Maksimal sesuai sisa anggaran: '.static::rupiah(static::sisaUntukValidasi($get, $record)).'.')
                    ->rule(static fn (Get $get, ?PengajuanProgramKerja $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                        $sisa = static::sisaUntukValidasi($get, $record);

                        if ((float) $value > $sisa) {
                            $fail('Pengajuan anggaran melebihi sisa anggaran unit kerja (tersisa '.static::rupiah($sisa).').');
                        }
                    })
                    ->columnSpanFull(),
                Grid::make(2)
                    ->schema([
                        DateTimePicker::make('estimasi_mulai')
                            ->label('Estimasi Mulai')
                            ->seconds(false)
                            ->helperText('Opsional.')
                            ->columnSpan(1),
                        DateTimePicker::make('estimasi_selesai')
                            ->label('Estimasi Selesai')
                            ->seconds(false)
                            ->helperText('Opsional. Harus setelah estimasi mulai.')
                            ->after('estimasi_mulai')
                            ->validationMessages([
                                'after' => 'Estimasi selesai harus setelah estimasi mulai.',
                            ])
                            ->columnSpan(1),
                    ])
                    ->columnSpanFull(),
                RichEditor::make('deskripsi_kegiatan')
                    ->label('Deskripsi Kegiatan')
                    ->required()
                    ->helperText('Jelaskan rencana kegiatan secara singkat. Wajib diisi.')
                    ->toolbarButtons([
                        'bold', 'italic', 'underline', 'strike',
                        'bulletList', 'orderedList', 'link', 'undo', 'redo',
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Kolom kanan: detail program kerja yang dipilih.
     */
    protected static function sidebar(): Group
    {
        return Group::make()
            ->columnSpan(1)
            ->schema([
                Section::make('Menunggu Pilihan Program Kerja')
                    ->description('Pilih program kerja terlebih dahulu untuk melihat detailnya. Berikut anggaran yang sudah dialokasikan pada unit kerja ini.')
                    ->visible(fn (Get $get): bool => ! static::hasPenawaran($get))
                    ->schema([
                        RepeatableEntry::make('menunggu_rincian_alokasi')
                            ->hiddenLabel()
                            ->state(fn (Get $get): array => static::rincianAlokasiRows($get))
                            ->table([
                                TableColumn::make('Program Kerja'),
                                TableColumn::make('Yang Mengajukan'),
                                TableColumn::make('Nominal')->alignment(Alignment::End),
                            ])
                            ->schema([
                                TextEntry::make('program')->placeholder('-'),
                                TextEntry::make('pemohon')->placeholder('-'),
                                TextEntry::make('nominal')->money('IDR')->alignment(Alignment::End),
                            ])
                            ->placeholder('Belum ada anggaran yang dialokasikan pada unit kerja ini.'),
                    ]),
                static::detailProgramKerjaSection(),
                static::pengajuanPenawaranSection(),
            ]);
    }

    /**
     * Detail program kerja terpilih, meniru infolist Daftar Program Kerja.
     */
    protected static function detailProgramKerjaSection(): Section
    {
        return Section::make('Detail Program Kerja')
            ->description('Rincian program kerja yang dipilih.')
            ->visible(fn (Get $get): bool => static::hasPenawaran($get))
            ->columns(2)
            ->schema([
                TextEntry::make('detail_name')
                    ->label('Nama Program Kerja')
                    ->state(fn (Get $get): ?string => static::penawaran($get)?->name)
                    ->columnSpanFull(),
                TextEntry::make('detail_tahun_kerja')
                    ->label('Tahun Kerja')
                    ->state(fn (Get $get): ?string => static::penawaran($get)?->tahunKerja?->name),
                TextEntry::make('detail_unit_kerja')
                    ->label('Unit Kerja')
                    ->state(fn (Get $get): ?string => static::penawaran($get)?->unitKerja?->name),
                TextEntry::make('detail_bidang')
                    ->label('Bidang')
                    ->state(fn (Get $get): ?string => static::penawaran($get)?->bidang?->name),
                TextEntry::make('detail_kategori')
                    ->label('Kategori')
                    ->state(fn (Get $get): ?string => static::penawaran($get)?->kategori?->name),
                TextEntry::make('detail_program')
                    ->label('Program Induk')
                    ->state(fn (Get $get): ?string => static::penawaran($get)?->program?->name),
                TextEntry::make('detail_rekening')
                    ->label('Kode Akun')
                    ->state(fn (Get $get): ?string => static::penawaran($get)?->rekening?->code)
                    ->placeholder('-'),
                TextEntry::make('detail_target')
                    ->label('Target')
                    ->state(fn (Get $get): ?string => static::penawaran($get)?->target)
                    ->placeholder('-'),
                TextEntry::make('detail_nilai_standar')
                    ->label('Nilai Standar')
                    ->state(fn (Get $get): ?string => static::nilaiStandar(static::penawaran($get)))
                    ->placeholder('-'),
                TextEntry::make('detail_aktifitas')
                    ->label('Aktivitas')
                    ->state(fn (Get $get): ?string => static::penawaran($get)?->aktifitas)
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('detail_indikator')
                    ->label('Indikator')
                    ->state(fn (Get $get): ?string => static::penawaran($get)?->indikator)
                    ->placeholder('-')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Section tersendiri berisi daftar pengajuan yang sudah ada pada program kerja
     * terpilih.
     */
    protected static function pengajuanPenawaranSection(): Section
    {
        return Section::make('Pengajuan pada Program Kerja Ini')
            ->description(fn (Get $get): ?string => static::infoPengajuanPenawaran($get))
            ->visible(fn (Get $get, string $operation): bool => $operation !== 'edit' && static::hasPenawaran($get))
            ->schema([
                RepeatableEntry::make('detail_pengajuan')
                    ->hiddenLabel()
                    ->state(fn (Get $get): Collection => static::pengajuanPenawaranRecords($get))
                    ->table([
                        TableColumn::make('Nominal'),
                        TableColumn::make('Status'),
                        TableColumn::make('Aksi')->alignment(Alignment::End),
                    ])
                    ->schema([
                        TextEntry::make('alokasi_anggaran')->money('IDR'),
                        TextEntry::make('status')->badge(),
                        Actions::make([
                            static::detailPengajuanAction(),
                        ]),
                    ])
                    ->placeholder('Belum ada pengajuan untuk program kerja ini.'),
            ]);
    }

    /**
     * Aksi lihat detail satu pengajuan: buka slide over berisi infolist pengajuan.
     */
    protected static function detailPengajuanAction(): Action
    {
        return Action::make('detailPengajuan')
            ->label('Lihat Detail')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->button()
            ->slideOver()
            ->modalHeading('Detail Pengajuan')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->schema([
                Grid::make(2)->schema([
                    TextEntry::make('penawaranProgramKerja.name')->label('Program Kerja')->columnSpanFull(),
                    TextEntry::make('unitKerja.name')->label('Unit Kerja'),
                    TextEntry::make('user.name')->label('Pemohon')->placeholder('-'),
                    TextEntry::make('alokasi_anggaran')->label('Pengajuan Anggaran')->money('IDR'),
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('estimasi_mulai')->label('Estimasi Mulai')->dateTime('d F Y H:i')->placeholder('-'),
                    TextEntry::make('estimasi_selesai')->label('Estimasi Selesai')->dateTime('d F Y H:i')->placeholder('-'),
                    TextEntry::make('deskripsi_kegiatan')->label('Deskripsi Kegiatan')->html()->placeholder('-')->columnSpanFull(),
                    TextEntry::make('catatan_verifikasi')->label('Catatan Verifikasi')->html()->placeholder('-')->columnSpanFull(),
                    TextEntry::make('created_at')->label('Dibuat')->dateTime('d F Y H:i'),
                ]),
            ]);
    }

    /**
     * Aksi header pada Ringkasan Anggaran: buka slide over berisi rincian pengajuan
     * yang membentuk dana teralokasi pada unit & tahun kerja terpilih.
     */
    protected static function rincianAlokasiAction(): Action
    {
        return Action::make('rincianAlokasi')
            ->label('Lihat Rincian Alokasi')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('gray')
            ->slideOver()
            ->modalHeading('Rincian Alokasi Anggaran')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->schema(fn (Get $get): array => [
                TextEntry::make('rincian_info')
                    ->hiddenLabel()
                    ->state('Daftar pengajuan (selain ditolak) pada unit & tahun kerja ini yang membentuk dana teralokasi.')
                    ->columnSpanFull(),
                RepeatableEntry::make('rincian_alokasi')
                    ->hiddenLabel()
                    ->state(static::rincianAlokasiRows($get))
                    ->table([
                        TableColumn::make('Program Kerja'),
                        TableColumn::make('Pemohon'),
                        TableColumn::make('Status'),
                        TableColumn::make('Nominal'),
                    ])
                    ->schema([
                        TextEntry::make('program')->placeholder('-'),
                        TextEntry::make('pemohon')->placeholder('-'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('nominal')->money('IDR'),
                    ])
                    ->placeholder('Belum ada alokasi pada unit & tahun kerja ini.')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Keterangan apakah program kerja terpilih sudah memiliki pengajuan.
     */
    protected static function infoPengajuanPenawaran(Get $get): ?string
    {
        $penawaran = static::penawaran($get);

        if ($penawaran === null) {
            return null;
        }

        $jumlah = PengajuanProgramKerja::query()
            ->where('penawaran_program_kerja_id', $penawaran->getKey())
            ->count();

        return $jumlah > 0
            ? "Sudah ada {$jumlah} pengajuan untuk program kerja ini."
            : 'Belum ada pengajuan untuk program kerja ini.';
    }

    /**
     * Record pengajuan pada program kerja terpilih, lengkap dengan relasi untuk
     * ditampilkan pada slide over detail.
     *
     * @return Collection<int, PengajuanProgramKerja>
     */
    protected static function pengajuanPenawaranRecords(Get $get): Collection
    {
        $penawaran = static::penawaran($get);

        if ($penawaran === null) {
            return new Collection;
        }

        return PengajuanProgramKerja::query()
            ->with(['unitKerja', 'user', 'penawaranProgramKerja'])
            ->where('penawaran_program_kerja_id', $penawaran->getKey())
            ->latest()
            ->get();
    }

    /**
     * Baris seluruh pengajuan (selain ditolak) pada unit & tahun kerja terpilih yang
     * membentuk dana teralokasi.
     *
     * @return array<int, array{program: string, pemohon: string, status: EnumStatusPengajuan, nominal: string}>
     */
    protected static function rincianAlokasiRows(Get $get): array
    {
        $unitKerjaId = static::unitKerjaId($get);
        $tahunKerjaId = static::tahunKerjaId($get);

        if ($unitKerjaId === null || $tahunKerjaId === null) {
            return [];
        }

        return PengajuanProgramKerja::query()
            ->with(['penawaranProgramKerja', 'user'])
            ->where('status', '!=', EnumStatusPengajuan::Ditolak->value)
            ->where('unit_kerja_id', $unitKerjaId)
            ->whereHas('penawaranProgramKerja', fn (Builder $query): Builder => $query
                ->where('tahun_kerja_id', $tahunKerjaId))
            ->latest()
            ->get()
            ->map(fn (PengajuanProgramKerja $pengajuan): array => [
                'program' => $pengajuan->penawaranProgramKerja?->name ?? '-',
                'pemohon' => $pengajuan->user?->name ?? '-',
                'status' => $pengajuan->status,
                'nominal' => $pengajuan->alokasi_anggaran,
            ])
            ->all();
    }

    protected static function hasPenawaran(Get $get): bool
    {
        return filled($get('penawaran_program_kerja_id'));
    }

    /**
     * Nilai standar digabung dengan satuannya, mis. "5 judul". Kosong bila nilai
     * standar belum diisi.
     */
    protected static function nilaiStandar(?PenawaranProgramKerja $penawaran): ?string
    {
        if ($penawaran === null || blank($penawaran->nilai_standar)) {
            return null;
        }

        return trim($penawaran->nilai_standar.' '.($penawaran->satuan_nilai_standar ?? ''));
    }

    /**
     * Program kerja (penawaran) yang sedang dipilih beserta relasi untuk detailnya.
     */
    protected static function penawaran(Get $get): ?PenawaranProgramKerja
    {
        $id = $get('penawaran_program_kerja_id');

        if (blank($id)) {
            return null;
        }

        $kunci = static::MEMO_PENAWARAN.(int) $id;

        if (! app()->bound($kunci)) {
            app()->instance($kunci, [
                'penawaran' => PenawaranProgramKerja::query()
                    ->with(['tahunKerja', 'unitKerja', 'bidang', 'kategori', 'program', 'rekening'])
                    ->find((int) $id),
            ]);
        }

        return app($kunci)['penawaran'];
    }

    /**
     * Unit kerja yang menjadi dasar ringkasan anggaran: pilihan di form, atau unit
     * kerja pengguna bila belum dipilih.
     */
    protected static function unitKerjaId(Get $get): ?int
    {
        $unitKerjaId = $get('unit_kerja_id') ?? auth()->user()?->unit_kerja_id;

        return $unitKerjaId !== null ? (int) $unitKerjaId : null;
    }

    /**
     * Tahun kerja yang menjadi dasar seluruh perhitungan anggaran form ini: tahun
     * milik penawaran yang sedang dipilih, bukan tahun kerja berjalan. Sistem
     * menjalankan tahun berjalan dan tahun perencanaan berdampingan, sehingga
     * pengajuan untuk tahun mendatang wajib diukur terhadap pagu tahun itu sendiri.
     * Jatuh kembali ke tahun berjalan selama penawaran belum dipilih, agar ringkasan
     * anggaran tetap terisi saat form baru dibuka.
     */
    protected static function tahunKerjaId(Get $get): ?int
    {
        $tahunKerjaId = static::penawaran($get)?->tahun_kerja_id
            ?? KonteksProgramKerja::tahunBerjalan()?->getKey();

        return $tahunKerjaId !== null ? (int) $tahunKerjaId : null;
    }

    /**
     * Total pagu anggaran unit kerja pada tahun kerja program yang dipilih.
     */
    protected static function totalAnggaran(Get $get): float
    {
        $unitKerjaId = static::unitKerjaId($get);
        $tahunKerjaId = static::tahunKerjaId($get);

        if ($unitKerjaId === null || $tahunKerjaId === null) {
            return 0.0;
        }

        return (float) (PaguAnggaran::query()
            ->where('tahun_kerja_id', $tahunKerjaId)
            ->where('unit_kerja_id', $unitKerjaId)
            ->value('amount') ?? 0);
    }

    /**
     * Dana yang sudah dialokasikan: jumlah alokasi seluruh pengajuan pada unit &
     * tahun kerja yang sama, dalam status apa pun kecuali ditolak. Saat menyunting,
     * pengajuan yang sedang diedit dikecualikan lewat $excludeId.
     */
    protected static function danaDialokasikan(Get $get, ?int $excludeId = null): float
    {
        $unitKerjaId = static::unitKerjaId($get);
        $tahunKerjaId = static::tahunKerjaId($get);

        if ($unitKerjaId === null || $tahunKerjaId === null) {
            return 0.0;
        }

        return (float) PengajuanProgramKerja::query()
            ->where('status', '!=', EnumStatusPengajuan::Ditolak->value)
            ->where('unit_kerja_id', $unitKerjaId)
            ->whereHas('penawaranProgramKerja', fn (Builder $query): Builder => $query
                ->where('tahun_kerja_id', $tahunKerjaId))
            ->when($excludeId !== null, fn (Builder $query): Builder => $query->whereKeyNot($excludeId))
            ->sum('alokasi_anggaran');
    }

    /**
     * Penyesuaian anggaran dari laporan realisasi unit kerja ini: sisa anggaran yang
     * sudah dikembalikan menambah anggaran yang bisa digunakan, kekurangan yang sudah
     * dilunasi menguranginya. Selisih yang masih menunggu Biro Keuangan belum dihitung.
     */
    protected static function penyesuaianRealisasi(Get $get): float
    {
        $unitKerjaId = static::unitKerjaId($get);
        $tahunKerjaId = static::tahunKerjaId($get);

        if ($unitKerjaId === null || $tahunKerjaId === null) {
            return 0.0;
        }

        return RealisasiProgramKerja::totalPenyesuaianAnggaran($unitKerjaId, $tahunKerjaId);
    }

    protected static function sisaAnggaran(Get $get): float
    {
        return static::totalAnggaran($get) - static::danaDialokasikan($get) + static::penyesuaianRealisasi($get);
    }

    /**
     * Sisa anggaran yang boleh dipakai pengajuan ini: total pagu dikurangi alokasi
     * pengajuan lain (pengajuan yang sedang diedit tidak ikut dihitung), ditambah
     * penyesuaian dari laporan realisasi yang selisih anggarannya sudah dituntaskan.
     */
    protected static function sisaUntukValidasi(Get $get, ?PengajuanProgramKerja $record): float
    {
        return static::totalAnggaran($get) - static::danaDialokasikan($get, $record?->getKey()) + static::penyesuaianRealisasi($get);
    }

    /**
     * Keterangan penyesuaian anggaran dari laporan realisasi pada kartu sisa anggaran.
     * Null bila belum ada selisih anggaran yang dituntaskan.
     */
    protected static function keteranganPenyesuaian(Get $get): ?string
    {
        $penyesuaian = static::penyesuaianRealisasi($get);

        if ($penyesuaian === 0.0) {
            return null;
        }

        return $penyesuaian > 0
            ? 'Termasuk tambahan '.static::rupiah($penyesuaian).' dari sisa anggaran realisasi yang sudah dikembalikan.'
            : 'Sudah dikurangi '.static::rupiah(abs($penyesuaian)).' untuk kekurangan anggaran realisasi yang sudah dilunasi.';
    }

    /**
     * Nominal rupiah beserta persentasenya terhadap total anggaran, mis.
     * "Rp 3.000.000 (30%)". Persentase disembunyikan saat total belum ada.
     */
    protected static function nominalDenganPersen(float $nilai, float $total): string
    {
        $rupiah = static::rupiah($nilai);

        if ($total <= 0) {
            return $rupiah;
        }

        return $rupiah.' ('.Number::percentage($nilai / $total * 100, maxPrecision: 1, locale: 'id').')';
    }

    protected static function rupiah(float $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }
}
