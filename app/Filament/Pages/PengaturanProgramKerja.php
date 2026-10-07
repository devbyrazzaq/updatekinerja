<?php

namespace App\Filament\Pages;

use App\Enums\EnumModeGenerate;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Forms\StateCasts\MoneyStateCast;
use App\Filament\Pages\Concerns\HasPageAuthorization;
use App\Filament\Resources\TahunKerjas\TahunKerjaResource;
use App\Models\KelompokAcuan;
use App\Models\Setting;
use App\Models\TahunKerja;
use App\Services\GeneratePenawaranFromAcuan;
use App\Services\TransisiTahunKerja;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use RuntimeException;
use UnitEnum;

/**
 * Menentukan dua slot konteks kerja yang hidup berdampingan: satu Tahun Kerja
 * Berjalan dan satu Tahun Kerja Perencanaan, masing-masing dipasangkan dengan
 * kelompok acuannya sendiri.
 *
 * Pemisahan inilah yang memungkinkan pagu, penawaran, dan pengajuan tahun
 * mendatang disusun tanpa menyembunyikan realisasi tahun yang sedang berjalan.
 * Slot perencanaan hanya membuka fase perencanaan; realisasi, pencairan, dan
 * pelaporan tetap terkunci sampai tahun tersebut benar-benar dijalankan lewat
 * bagian Transisi Tahun Kerja.
 *
 * @property-read Schema $form
 */
class PengaturanProgramKerja extends Page
{
    use HasPageAuthorization;

    protected string $view = 'filament.pages.pengaturan-program-kerja';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Pengaturan Program Kerja';

    protected static ?string $title = 'Pengaturan Program Kerja';

    protected static ?int $navigationSort = 1;

    /**
     * Metadata untuk form Role & Hak Akses (dibaca PermissionRegistrar).
     *
     * @var array<string, mixed>
     */
    public static array $permissions = [
        'heading' => 'Pengaturan Program Kerja',
        'description' => 'Hak akses untuk menetapkan tahun kerja berjalan dan tahun kerja perencanaan beserta kelompok acuannya, membentuk penawaran program kerja dari acuan, serta menjalankan transisi antar tahun kerja.',
        'permission_descriptions' => [
            'view_page_pengaturan_program_kerja' => 'Menetapkan kelompok acuan & tahun kerja pada slot berjalan maupun perencanaan, menjalankan generate penawaran, dan melakukan transisi tahun kerja.',
        ],
    ];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Pengaturan Sistem';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Tahun kerja berjalan membuka seluruh menu; tahun kerja perencanaan hanya membuka Pagu Anggaran, Penawaran, Pengajuan, dan Verifikasi Pengajuan. Realisasi dan pencairan tetap milik tahun berjalan.';
    }

    public function mount(): void
    {
        $this->form->fill($this->nilaiAwal());
    }

    /**
     * Isi awal form dari kedua slot konteks.
     *
     * @return array<string, mixed>
     */
    protected function nilaiAwal(): array
    {
        $nilai = [];

        foreach (static::slots() as $slot) {
            $prefix = $slot->value;
            $tahunKerja = static::tahunKerjaSlot($slot);

            $nilai["{$prefix}_tahun_kerja_id"] = $tahunKerja?->getKey();
            $nilai["{$prefix}_kelompok_acuan_id"] = $tahunKerja?->kelompok_acuan_id ?? KelompokAcuan::active()?->getKey();
            $nilai["{$prefix}_batas_anggaran"] = $tahunKerja?->batas_anggaran;
            $nilai["{$prefix}_referensi_tahun_kerja_id"] = $tahunKerja?->referensi_tahun_kerja_id;
        }

        return $nilai;
    }

    /**
     * Slot konteks yang dikelola halaman ini, sesuai urutan tampilnya.
     *
     * @return array<int, EnumStatusTahunKerja>
     */
    protected static function slots(): array
    {
        return [EnumStatusTahunKerja::Berjalan, EnumStatusTahunKerja::Perencanaan];
    }

    protected static function tahunKerjaSlot(EnumStatusTahunKerja $slot): ?TahunKerja
    {
        return $slot === EnumStatusTahunKerja::Berjalan
            ? TahunKerja::berjalan()
            : TahunKerja::perencanaan();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->slotSection(EnumStatusTahunKerja::Berjalan),
                $this->slotSection(EnumStatusTahunKerja::Perencanaan),
                $this->transisiSection(),
            ])
            ->statePath('data');
    }

    /**
     * Satu bagian pengaturan untuk satu slot konteks, ditampilkan sebagai ringkasan
     * baca-saja. Isiannya diubah lewat tombol Ubah, lalu benar-benar dijalankan lewat
     * tombol Terapkan — memisahkan keduanya membuat perubahan pilihan tidak pernah
     * tertukar dengan tindakan yang menggeser slot dan membentuk ulang penawaran.
     */
    protected function slotSection(EnumStatusTahunKerja $slot): Section
    {
        $prefix = $slot->value;
        $berjalan = $slot === EnumStatusTahunKerja::Berjalan;

        return Section::make($berjalan ? 'Tahun Kerja Berjalan' : 'Tahun Kerja Perencanaan')
            ->description($berjalan
                ? 'Tahun kerja yang sedang dijalankan. Seluruh menu Program Kerja, Pelaksanaan, dan Verifikasi terbuka untuk tahun ini.'
                : 'Tahun kerja mendatang yang sedang disiapkan. Hanya Pagu Anggaran, Penawaran, Pengajuan, dan Verifikasi Pengajuan yang terbuka; realisasi baru bisa berjalan setelah tahun ini dimulai.')
            ->icon($berjalan ? Heroicon::OutlinedPlayCircle : Heroicon::OutlinedCalendarDays)
            ->columnSpanFull()
            ->schema([
                Grid::make(2)->schema([
                    TextEntry::make("{$prefix}_kelompok_acuan_id")
                        ->label('Kelompok Acuan')
                        ->placeholder('Belum dipilih')
                        ->formatStateUsing(function (mixed $state): ?string {
                            $kelompokAcuan = KelompokAcuan::find($state);

                            return $kelompokAcuan instanceof KelompokAcuan
                                ? "{$kelompokAcuan->name} ({$kelompokAcuan->tahun_mulai}-{$kelompokAcuan->tahun_selesai})"
                                : null;
                        })
                        ->columnSpan(1),
                    TextEntry::make("{$prefix}_tahun_kerja_id")
                        ->label('Tahun Kerja')
                        ->placeholder('Belum dipilih')
                        ->formatStateUsing(fn (mixed $state): ?string => TahunKerja::find($state)?->name)
                        ->columnSpan(1),
                    TextEntry::make("{$prefix}_batas_anggaran")
                        ->label('Batas Anggaran Tahun Kerja')
                        ->placeholder('Belum diisi')
                        ->formatStateUsing(fn (mixed $state): ?string => blank($state)
                            ? null
                            : 'Rp '.number_format((float) MoneyStateCast::unformat($state), 0, ',', '.'))
                        ->columnSpan(1),
                    TextEntry::make("{$prefix}_referensi_tahun_kerja_id")
                        ->label('Tahun Anggaran Referensi')
                        ->placeholder('Tidak ada referensi')
                        ->formatStateUsing(fn (mixed $state): ?string => TahunKerja::find($state)?->name)
                        ->columnSpan(1),
                ]),
                Placeholder::make("{$prefix}_ringkasan")
                    ->label('Ringkasan Slot Terpilih')
                    ->content(fn (Get $get): Htmlable => $this->ringkasanSlot(
                        $slot,
                        KelompokAcuan::find($get("{$prefix}_kelompok_acuan_id")),
                        TahunKerja::find($get("{$prefix}_tahun_kerja_id")),
                    ))
                    ->columnSpanFull(),
                Actions::make([
                    $berjalan ? $this->ubahBerjalanAction() : $this->ubahPerencanaanAction(),
                    $berjalan ? $this->terapkanBerjalanAction() : $this->terapkanPerencanaanAction(),
                ]),
            ]);
    }

    /**
     * Bagian perpindahan slot. Mengakhiri tahun kerja ditempuh dua langkah yang
     * sengaja dipisah: Akhiri menyetop pengajuan dan realisasi baru, lalu Kunci
     * menjadikannya hanya-baca setelah seluruh tunggakannya tuntas.
     */
    protected function transisiSection(): Section
    {
        return Section::make('Transisi Tahun Kerja')
            ->description('Mengakhiri tahun kerja yang sedang berjalan, menjalankan tahun yang sudah direncanakan, dan mengunci tahun lama yang seluruh realisasinya sudah tuntas.')
            ->icon(Heroicon::OutlinedArrowPath)
            ->columnSpanFull()
            ->schema([
                Placeholder::make('ringkasan_transisi')
                    ->label('Status Tahun Kerja Saat Ini')
                    ->content(fn (): Htmlable => $this->ringkasanTransisi())
                    ->columnSpanFull(),
                Actions::make([
                    $this->akhiriTahunKerjaAction(),
                    $this->mulaiTahunPerencanaanAction(),
                    $this->kunciTahunKerjaAction(),
                    $this->batalkanPenutupanAction(),
                ]),
            ]);
    }

    /**
     * Tahun kerja yang boleh menempati sebuah slot: seluruh tahun kecuali yang
     * sedang menempati slot seberangnya.
     *
     * @return array<int, string>
     */
    protected function opsiTahunKerja(EnumStatusTahunKerja $slot): array
    {
        $seberang = $slot === EnumStatusTahunKerja::Berjalan
            ? TahunKerja::perencanaan()
            : TahunKerja::berjalan();

        return TahunKerja::query()
            ->when($seberang, fn ($query, TahunKerja $tahunKerja) => $query->whereKeyNot($tahunKerja->getKey()))
            ->orderByDesc('start_datetime')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Filament menemukan aksi halaman lewat metode bernama `{nama}Action()` tanpa
     * argumen, sehingga tiap slot butuh pintu masuknya sendiri.
     */
    public function terapkanBerjalanAction(): Action
    {
        return $this->terapkanAction(EnumStatusTahunKerja::Berjalan);
    }

    public function terapkanPerencanaanAction(): Action
    {
        return $this->terapkanAction(EnumStatusTahunKerja::Perencanaan);
    }

    public function ubahBerjalanAction(): Action
    {
        return $this->ubahAction(EnumStatusTahunKerja::Berjalan);
    }

    public function ubahPerencanaanAction(): Action
    {
        return $this->ubahAction(EnumStatusTahunKerja::Perencanaan);
    }

    /**
     * Mengubah pilihan sebuah slot tanpa menjalankan apa pun. Perubahan berhenti di
     * ringkasan halaman sampai tombol Terapkan ditekan, sehingga mengoreksi pilihan
     * tidak pernah ikut menggeser slot atau membentuk ulang penawaran.
     */
    protected function ubahAction(EnumStatusTahunKerja $slot): Action
    {
        $prefix = $slot->value;
        $berjalan = $slot === EnumStatusTahunKerja::Berjalan;

        return Action::make($berjalan ? 'ubahBerjalan' : 'ubahPerencanaan')
            ->label('Ubah')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->modalHeading($berjalan ? 'Ubah Tahun Kerja Berjalan' : 'Ubah Tahun Kerja Perencanaan')
            ->modalDescription('Perubahan di sini belum menjalankan apa pun. Setelah tersimpan, tekan Terapkan untuk benar-benar menempatkan slot dan membentuk penawarannya.')
            ->modalSubmitActionLabel('Simpan Pilihan')
            ->modalWidth('2xl')
            ->fillForm(fn (): array => [
                'kelompok_acuan_id' => $this->data["{$prefix}_kelompok_acuan_id"] ?? null,
                'tahun_kerja_id' => $this->data["{$prefix}_tahun_kerja_id"] ?? null,
                'batas_anggaran' => $this->data["{$prefix}_batas_anggaran"] ?? null,
                'referensi_tahun_kerja_id' => $this->data["{$prefix}_referensi_tahun_kerja_id"] ?? null,
            ])
            ->schema(fn (): array => $this->ubahSchema($slot))
            ->action(function (array $data) use ($prefix): void {
                $this->data["{$prefix}_kelompok_acuan_id"] = $data['kelompok_acuan_id'] ?? null;
                $this->data["{$prefix}_tahun_kerja_id"] = $data['tahun_kerja_id'] ?? null;
                $this->data["{$prefix}_batas_anggaran"] = $data['batas_anggaran'] ?? null;
                $this->data["{$prefix}_referensi_tahun_kerja_id"] = $data['referensi_tahun_kerja_id'] ?? null;
            });
    }

    /**
     * Isian satu slot. Sama persis dengan isian yang dulu tampil langsung di halaman,
     * hanya pindah ke dalam modal Ubah.
     *
     * @return array<int, mixed>
     */
    protected function ubahSchema(EnumStatusTahunKerja $slot): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('kelompok_acuan_id')
                    ->label('Kelompok Acuan')
                    ->options(fn (): array => KelompokAcuan::query()
                        ->orderByDesc('tahun_mulai')
                        ->get()
                        ->mapWithKeys(fn (KelompokAcuan $kelompokAcuan): array => [
                            $kelompokAcuan->getKey() => "{$kelompokAcuan->name} ({$kelompokAcuan->tahun_mulai}-{$kelompokAcuan->tahun_selesai})",
                        ])
                        ->all())
                    ->required()
                    ->live()
                    ->native(false)
                    ->helperText('Menentukan acuan program kerja mana yang menjadi sumber penawaran tahun ini.')
                    ->columnSpan(1),
                Select::make('tahun_kerja_id')
                    ->label('Tahun Kerja')
                    ->options(fn (): array => $this->opsiTahunKerja($slot))
                    ->placeholder(fn (): string => $this->opsiTahunKerja($slot) === []
                        ? 'Belum ada tahun kerja yang bisa dipilih'
                        : 'Pilih tahun kerja')
                    ->required()
                    ->live()
                    ->native(false)
                    ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                        $set('batas_anggaran', TahunKerja::find($state)?->batas_anggaran);

                        if ($get('referensi_tahun_kerja_id') == $state) {
                            $set('referensi_tahun_kerja_id', null);
                        }
                    })
                    ->helperText(fn (): string|Htmlable => $this->keteranganTahunKerja($slot))
                    ->columnSpan(1),
                MoneyInput::make('batas_anggaran')
                    ->label('Batas Anggaran Tahun Kerja')
                    ->helperText('Total nominal anggaran untuk tahun kerja ini. Dipakai sebagai pembanding total pengajuan dan penggunaan anggaran pada ringkasan Pagu Anggaran. Nilai disimpan saat slot diterapkan.')
                    ->columnSpan(1),
                Select::make('referensi_tahun_kerja_id')
                    ->label('Tahun Anggaran Referensi')
                    ->placeholder('Tidak ada referensi')
                    ->options(fn (Get $get): array => TahunKerja::query()
                        ->when($get('tahun_kerja_id'), fn ($query, $tahunKerjaId) => $query->whereKeyNot($tahunKerjaId))
                        ->orderByDesc('start_datetime')
                        ->pluck('name', 'id')
                        ->all())
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->helperText('Opsional. Bila diisi, halaman Pagu Anggaran menampilkan kolom anggaran unit kerja dari tahun ini sebagai pembanding — berguna untuk menyusun pagu tahun mendatang.')
                    ->columnSpan(1),
            ]),
        ];
    }

    /**
     * Menerapkan satu slot sekaligus menjalankan generate penawaran. Modal-nya
     * menuntut verifikasi: pilihan cara pembentukan (dan captcha bila menghapus data).
     */
    protected function terapkanAction(EnumStatusTahunKerja $slot): Action
    {
        $berjalan = $slot === EnumStatusTahunKerja::Berjalan;

        return Action::make($berjalan ? 'terapkanBerjalan' : 'terapkanPerencanaan')
            ->label($berjalan ? 'Terapkan Tahun Berjalan' : 'Terapkan Tahun Perencanaan')
            ->icon(Heroicon::OutlinedSparkles)
            ->disabled(fn (): bool => $this->kelompokAcuanTerpilih($slot) === null
                || $this->tahunKerjaTerpilih($slot) === null
                || $this->menggeserTahunBerjalan($slot))
            ->modalHeading($berjalan ? 'Terapkan Tahun Kerja Berjalan' : 'Terapkan Tahun Kerja Perencanaan')
            ->modalDescription('Periksa ringkasan di bawah, lalu tentukan bagaimana penawaran program kerja dibentuk untuk tahun kerja ini.')
            ->modalSubmitActionLabel('Ya, Terapkan')
            ->modalWidth('2xl')
            ->schema(fn (): array => $this->terapkanSchema($slot))
            ->action(fn (array $data, Action $action) => $this->terapkan($slot, $data, $action));
    }

    /**
     * Mengakhiri tahun kerja yang sedang berjalan tanpa menunggu tahun pengganti.
     * Sejak saat itu seluruh menu Pelaksanaan dan Verifikasi kosong sampai tahun
     * baru dijalankan, dan tunggakannya dikerjakan di Penyelesaian Tahun Lalu.
     */
    public function akhiriTahunKerjaAction(): Action
    {
        return Action::make('akhiriTahunKerja')
            ->label('Akhiri Tahun Kerja Berjalan')
            ->icon(Heroicon::OutlinedStopCircle)
            ->color('danger')
            ->disabled(fn (): bool => TahunKerja::berjalan() === null)
            ->modalHeading('Akhiri Tahun Kerja Berjalan')
            ->modalDescription('Pengajuan dan realisasi baru ditutup. Realisasi yang sudah berjalan tidak hilang — pekerjaannya pindah ke halaman Penyelesaian Tahun Lalu sampai tahun kerja ini boleh dikunci.')
            ->modalSubmitActionLabel('Ya, Akhiri')
            ->modalWidth('2xl')
            ->schema(fn (): array => $this->akhiriSchema())
            ->action(function (array $data, Action $action): void {
                $tahunKerja = TahunKerja::berjalan();

                if ($tahunKerja === null) {
                    $this->gagal('Belum ada tahun kerja yang sedang berjalan.', $action);
                }

                if ((int) ($data['captcha_answer'] ?? 0) !== (int) ($data['captcha_count'] ?? 0)) {
                    $this->gagal('Jawaban konfirmasi salah. Tahun kerja tidak diakhiri.', $action);
                }

                try {
                    app(TransisiTahunKerja::class)->akhiriTahunKerja($tahunKerja);
                } catch (RuntimeException $exception) {
                    $this->gagal($exception->getMessage(), $action);
                }

                $this->form->fill($this->nilaiAwal());

                Notification::make()
                    ->title("{$tahunKerja->name} berakhir")
                    ->body('Menu Pelaksanaan dan Verifikasi kosong sampai tahun kerja baru dijalankan. '
                        .'Tunggakan tahun ini dikerjakan di halaman Penyelesaian Tahun Lalu, dan setelah tuntas tahun kerja boleh dikunci.')
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    /**
     * Menarik kembali pengakhiran yang terlanjur dijalankan. Mengakhiri tahun kerja
     * tidak menghapus data apa pun, jadi pemulihannya cukup mengembalikan status.
     */
    public function batalkanPenutupanAction(): Action
    {
        return Action::make('batalkanPenutupan')
            ->label('Batalkan Penutupan')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->disabled(fn (): bool => TahunKerja::berjalan() !== null || TahunKerja::penutupan()->isEmpty())
            ->modalHeading('Batalkan Penutupan Tahun Kerja')
            ->modalDescription('Tahun kerja kembali berjalan beserta seluruh menu Pelaksanaan dan Verifikasinya. Hanya bisa dijalankan selama slot berjalan masih kosong.')
            ->modalSubmitActionLabel('Ya, Jalankan Kembali')
            ->schema(fn (): array => [
                Select::make('tahun_kerja_id')
                    ->label('Tahun Kerja')
                    ->options(fn (): array => TahunKerja::penutupan()->pluck('name', 'id')->all())
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data, Action $action): void {
                $tahunKerja = TahunKerja::find($data['tahun_kerja_id'] ?? null);

                if ($tahunKerja === null) {
                    $this->gagal('Tahun kerja yang akan dijalankan kembali tidak ditemukan.', $action);
                }

                try {
                    app(TransisiTahunKerja::class)->batalkanPenutupan($tahunKerja);
                } catch (RuntimeException $exception) {
                    $this->gagal($exception->getMessage(), $action);
                }

                $this->form->fill($this->nilaiAwal());

                Notification::make()
                    ->title("{$tahunKerja->name} kembali berjalan")
                    ->body('Pengajuan dan realisasi baru terbuka lagi seperti sebelum tahun kerja ini diakhiri.')
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    /**
     * Menjalankan tahun kerja yang sedang direncanakan. Tahun berjalan sebelumnya
     * bergeser ke Penutupan supaya realisasinya masih bisa dituntaskan.
     */
    public function mulaiTahunPerencanaanAction(): Action
    {
        return Action::make('mulaiTahunPerencanaan')
            ->label('Mulai Tahun Perencanaan')
            ->icon(Heroicon::OutlinedPlayCircle)
            ->color('success')
            ->disabled(fn (): bool => TahunKerja::perencanaan() === null)
            ->requiresConfirmation()
            ->modalHeading('Mulai Tahun Kerja yang Direncanakan')
            ->modalDescription(fn (): Htmlable => $this->ringkasanMulai())
            ->modalSubmitActionLabel('Ya, Jalankan')
            ->action(function (Action $action): void {
                $tahunKerja = TahunKerja::perencanaan();

                if ($tahunKerja === null) {
                    $this->gagal('Belum ada tahun kerja yang direncanakan.', $action);
                }

                $sebelumnya = TahunKerja::berjalan();

                try {
                    app(TransisiTahunKerja::class)->mulaiTahunKerja($tahunKerja);
                } catch (RuntimeException $exception) {
                    $this->gagal($exception->getMessage(), $action);
                }

                $this->form->fill($this->nilaiAwal());

                Notification::make()
                    ->title("{$tahunKerja->name} kini berjalan")
                    ->body($sebelumnya === null
                        ? 'Seluruh menu kini mengikuti tahun kerja ini.'
                        : "{$sebelumnya->name} berpindah ke status Penutupan: pengajuan barunya ditutup, tetapi realisasi yang sudah berjalan masih bisa dituntaskan.")
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    /**
     * Mengunci tahun kerja yang seluruh realisasinya sudah tuntas.
     */
    public function kunciTahunKerjaAction(): Action
    {
        return Action::make('kunciTahunKerja')
            ->label('Kunci Tahun Kerja')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('warning')
            ->disabled(fn (): bool => TahunKerja::penutupan()->isEmpty())
            ->modalHeading('Kunci Tahun Kerja')
            ->modalDescription('Tahun kerja yang dikunci menjadi hanya-baca. Penguncian ditolak selama masih ada realisasi berjalan atau selisih anggaran yang menunggu Biro Keuangan.')
            ->modalSubmitActionLabel('Ya, Kunci')
            ->schema(fn (): array => [
                Select::make('tahun_kerja_id')
                    ->label('Tahun Kerja')
                    ->options(fn (): array => TahunKerja::penutupan()->pluck('name', 'id')->all())
                    ->required()
                    ->live()
                    ->native(false),
                Placeholder::make('penghambat')
                    ->label('Pemeriksaan')
                    ->content(fn (Get $get): Htmlable => $this->ringkasanPenguncian($get('tahun_kerja_id'))),
            ])
            ->action(function (array $data, Action $action): void {
                $tahunKerja = TahunKerja::find($data['tahun_kerja_id'] ?? null);

                if ($tahunKerja === null) {
                    $this->gagal('Tahun kerja yang akan dikunci tidak ditemukan.', $action);
                }

                try {
                    app(TransisiTahunKerja::class)->kunciTahunKerja($tahunKerja);
                } catch (RuntimeException $exception) {
                    $this->gagal($exception->getMessage(), $action);
                }

                Notification::make()
                    ->title("{$tahunKerja->name} terkunci")
                    ->body('Data tahun kerja ini kini hanya bisa dibaca untuk keperluan laporan.')
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    /**
     * Isi modal Akhiri Tahun Kerja: apa yang ikut dibekukan, lalu captcha karena
     * pengakhiran menghentikan pekerjaan seluruh unit kerja sekaligus.
     *
     * @return array<int, mixed>
     */
    protected function akhiriSchema(): array
    {
        $captcha = $this->buatCaptcha();

        return [
            Placeholder::make('dampak')
                ->label('Dampak')
                ->content(fn (): Htmlable => $this->ringkasanPengakhiran()),
            Hidden::make('captcha_count')
                ->default($captcha['answer'])
                ->dehydrated(),
            TextInput::make('captcha_answer')
                ->label('Konfirmasi Pengakhiran')
                ->numeric()
                ->prefix($captcha['text'])
                ->helperText('Ketik hasil operasi untuk melanjutkan.')
                ->required(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function terapkanSchema(EnumStatusTahunKerja $slot): array
    {
        $kelompokAcuan = $this->kelompokAcuanTerpilih($slot);
        $tahunKerja = $this->tahunKerjaTerpilih($slot);

        if ($kelompokAcuan === null || $tahunKerja === null) {
            return [];
        }

        $jumlahPenawaran = GeneratePenawaranFromAcuan::jumlahPenawaran($tahunKerja);
        $jumlahBerpengajuan = GeneratePenawaranFromAcuan::jumlahPenawaranBerpengajuan($tahunKerja);
        $captcha = $this->buatCaptcha();

        $modes = $jumlahPenawaran === 0
            ? [EnumModeGenerate::Baru]
            : [EnumModeGenerate::Lewati, EnumModeGenerate::Sinkron, EnumModeGenerate::Ulang];

        return [
            Placeholder::make('ringkasan')
                ->label('Ringkasan')
                ->content($this->ringkasanKonteks($kelompokAcuan, $tahunKerja)),
            Radio::make('mode')
                ->label('Cara Pembentukan Penawaran')
                ->options(collect($modes)->mapWithKeys(fn (EnumModeGenerate $mode): array => [$mode->value => $mode->getLabel()])->all())
                ->descriptions(collect($modes)->mapWithKeys(fn (EnumModeGenerate $mode): array => [$mode->value => $mode->getDescription()])->all())
                ->default($modes[0]->value)
                ->required()
                ->live()
                ->disableOptionWhen(fn (string $value): bool => $value === EnumModeGenerate::Ulang->value && $jumlahBerpengajuan > 0)
                ->helperText($jumlahBerpengajuan > 0
                    ? new HtmlString("<strong>Generate ulang terkunci</strong>: {$jumlahBerpengajuan} penawaran sudah memiliki pengajuan, sehingga penawaran tahun kerja ini tidak boleh dihapus.")
                    : null),
            Toggle::make('only_with_target')
                ->label('Hanya acuan yang memiliki target untuk tahun ini')
                ->helperText('Acuan tanpa target pada tahun kerja terpilih akan dilewati.')
                ->default(true)
                ->visible(fn (Get $get): bool => in_array($get('mode'), [EnumModeGenerate::Baru->value, EnumModeGenerate::Sinkron->value, EnumModeGenerate::Ulang->value], true)),
            Hidden::make('captcha_count')
                ->default($captcha['answer'])
                ->dehydrated(),
            TextInput::make('captcha_answer')
                ->label('Konfirmasi Generate Ulang')
                ->numeric()
                ->prefix($captcha['text'])
                ->helperText('Generate ulang menghapus seluruh penawaran tahun kerja ini. Ketik hasil operasi untuk melanjutkan.')
                ->required(fn (Get $get): bool => $get('mode') === EnumModeGenerate::Ulang->value)
                ->visible(fn (Get $get): bool => $get('mode') === EnumModeGenerate::Ulang->value),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function terapkan(EnumStatusTahunKerja $slot, array $data, Action $action): void
    {
        $prefix = $slot->value;
        $kelompokAcuan = $this->kelompokAcuanTerpilih($slot);
        $tahunKerja = $this->tahunKerjaTerpilih($slot);

        if ($kelompokAcuan === null || $tahunKerja === null) {
            $this->gagal('Kelompok acuan dan tahun kerja wajib dipilih.', $action);
        }

        $mode = EnumModeGenerate::from($data['mode']);

        if ($mode === EnumModeGenerate::Ulang && (int) ($data['captcha_answer'] ?? 0) !== (int) ($data['captcha_count'] ?? 0)) {
            $this->gagal('Jawaban konfirmasi salah. Silakan coba lagi.', $action);
        }

        $batasAnggaran = MoneyStateCast::unformat($this->data["{$prefix}_batas_anggaran"] ?? null);
        $referensiTahunKerjaId = $this->data["{$prefix}_referensi_tahun_kerja_id"] ?? null;

        // Slot ditempatkan lewat service transisi supaya invariant dua slot terjaga
        // dan tahun berjalan sebelumnya bergeser ke Penutupan, bukan hilang begitu saja.
        try {
            $transisi = app(TransisiTahunKerja::class);

            $slot === EnumStatusTahunKerja::Berjalan
                ? $transisi->mulaiTahunKerja($tahunKerja)
                : $transisi->tetapkanPerencanaan($tahunKerja);
        } catch (RuntimeException $exception) {
            $this->gagal($exception->getMessage(), $action);
        }

        $tahunKerja->update([
            'kelompok_acuan_id' => $kelompokAcuan->getKey(),
            'batas_anggaran' => blank($batasAnggaran) ? null : $batasAnggaran,
            'referensi_tahun_kerja_id' => ($referensiTahunKerjaId == $tahunKerja->getKey()) ? null : ($referensiTahunKerjaId ?: null),
        ]);

        // Kelompok acuan slot berjalan tetap ditandai aktif karena menu Acuan Program
        // Kerja masih memakainya sebagai nilai bawaan saat menambah data baru.
        if ($slot === EnumStatusTahunKerja::Berjalan) {
            $kelompokAcuan->update(['is_active' => true]);
        }

        try {
            $hasil = app(GeneratePenawaranFromAcuan::class)
                ->handle($kelompokAcuan, $tahunKerja, $mode, (bool) ($data['only_with_target'] ?? true));
        } catch (RuntimeException $exception) {
            $this->gagal($exception->getMessage(), $action);
        }

        $this->form->fill($this->nilaiAwal());

        Notification::make()
            ->title($slot === EnumStatusTahunKerja::Berjalan ? 'Tahun kerja berjalan diterapkan' : 'Tahun kerja perencanaan diterapkan')
            ->body("{$kelompokAcuan->name} — {$tahunKerja->name} kini menempati slot {$slot->getLabel()}. "
                .($mode === EnumModeGenerate::Lewati
                    ? 'Penawaran yang sudah ada dibiarkan apa adanya.'
                    : "Penawaran: {$hasil['created']} dibuat, {$hasil['updated']} diperbarui, {$hasil['deleted']} dihapus, {$hasil['skipped']} dilewati."))
            ->success()
            ->persistent()
            ->send();

        // Lepas fokus dari input setelah modal selesai menutup. Modal mengembalikan
        // fokus ke elemen pemicu di akhir transisinya, sehingga blur harus ditunda
        // agar tidak keburu di-override oleh focus-trap.
        $this->js(<<<'JS'
            setTimeout(() => {
                if (document.activeElement instanceof HTMLElement) {
                    document.activeElement.blur();
                }
            }, 350);
        JS);
    }

    /**
     * Hentikan aksi dengan notifikasi kegagalan; konteks & penawaran tidak berubah.
     */
    protected function gagal(string $pesan, Action $action): never
    {
        Notification::make()
            ->title('Gagal menerapkan konteks')
            ->body($pesan)
            ->danger()
            ->persistent()
            ->send();

        $action->halt();
    }

    /**
     * Keterangan di bawah pilihan Tahun Kerja. Saat tidak ada kandidat yang tersisa,
     * keterangan bawaan diganti alasan kenapa daftarnya kosong berikut jalan keluarnya —
     * tanpa itu tombol "Terapkan" terlihat mati tanpa sebab.
     */
    protected function keteranganTahunKerja(EnumStatusTahunKerja $slot): string|Htmlable
    {
        if ($this->opsiTahunKerja($slot) !== []) {
            return $slot === EnumStatusTahunKerja::Berjalan
                ? 'Tahun kerja yang datanya tampil di seluruh menu.'
                : 'Tahun kerja yang sedang direncanakan. Tidak boleh sama dengan tahun kerja berjalan.';
        }

        $seberang = $slot === EnumStatusTahunKerja::Berjalan
            ? TahunKerja::perencanaan()
            : TahunKerja::berjalan();

        $sebab = $seberang === null
            ? 'Belum ada satu pun tahun kerja yang terdaftar.'
            : 'Satu-satunya tahun kerja yang ada, <strong>'.e($seberang->name).'</strong>, sedang menempati slot '
                .e($seberang->status->getLabel()).' sehingga tidak bisa dipakai di slot ini.';

        return new HtmlString('<span class="text-warning-600"><strong>Belum ada tahun kerja yang bisa dipilih.</strong> '
            .$sebab.' '.$this->tautanTahunKerja().'</span>');
    }

    /**
     * Ringkasan satu slot: bila belum lengkap, sebutkan isian mana yang masih kosong
     * agar jelas apa yang menahan tombol "Terapkan".
     */
    protected function ringkasanSlot(EnumStatusTahunKerja $slot, ?KelompokAcuan $kelompokAcuan, ?TahunKerja $tahunKerja): Htmlable
    {
        if ($this->menggeserTahunBerjalan($slot)) {
            $pemegang = TahunKerja::berjalan();

            return new HtmlString("<span class=\"text-warning-600\"><strong>{$pemegang->name} sedang berjalan.</strong> "
                .'Mengganti tahun berjalan tidak dilakukan dari sini agar tahun lama tidak bergeser diam-diam: akhiri dulu lewat tombol '
                .'<strong>Akhiri Tahun Kerja Berjalan</strong> di bagian Transisi Tahun Kerja, baru tahun penggantinya diterapkan.</span>');
        }

        if ($kelompokAcuan !== null && $tahunKerja !== null) {
            return $this->ringkasanKonteks($kelompokAcuan, $tahunKerja);
        }

        if ($tahunKerja === null && $this->opsiTahunKerja($slot) === []) {
            return new HtmlString('<span class="text-warning-600">Slot ini belum bisa diterapkan karena tidak ada tahun kerja yang tersedia untuk ditempatkan di sini. '
                .$this->tautanTahunKerja().'</span>');
        }

        $kurang = collect([
            $kelompokAcuan === null ? 'kelompok acuan' : null,
            $tahunKerja === null ? 'tahun kerja' : null,
        ])->filter()->implode(' dan ');

        return new HtmlString("<span class=\"text-gray-500\">Isi {$kurang} lewat tombol Ubah untuk melihat ringkasan dampaknya. Tombol Terapkan aktif setelah keduanya terisi.</span>");
    }

    /**
     * Petunjuk menambah tahun kerja, ditautkan hanya bila pengguna memang boleh
     * membuka menunya.
     */
    protected function tautanTahunKerja(): string
    {
        if (! TahunKerjaResource::canViewAny()) {
            return 'Minta admin menambahkan tahun kerja baru terlebih dahulu.';
        }

        $url = TahunKerjaResource::getUrl('index');

        return "Tambahkan tahun kerja baru lewat menu <a href=\"{$url}\" class=\"font-medium underline\">Tahun Kerja</a> terlebih dahulu.";
    }

    protected function ringkasanKonteks(?KelompokAcuan $kelompokAcuan, ?TahunKerja $tahunKerja): Htmlable
    {
        if ($kelompokAcuan === null || $tahunKerja === null) {
            return new HtmlString('<span class="text-gray-500">Pilih kelompok acuan dan tahun kerja untuk melihat ringkasan dampaknya.</span>');
        }

        $jumlahAcuan = GeneratePenawaranFromAcuan::jumlahAcuan($kelompokAcuan);
        $jumlahPenawaran = GeneratePenawaranFromAcuan::jumlahPenawaran($tahunKerja);
        $jumlahBerpengajuan = GeneratePenawaranFromAcuan::jumlahPenawaranBerpengajuan($tahunKerja);
        $tahunTarget = $tahunKerja->tahunTarget();
        $dalamKelompok = in_array($tahunTarget, $kelompokAcuan->tahunList(), true)
            ? ''
            : "<li class=\"text-warning-600\"><strong>Perhatian:</strong> tahun {$tahunTarget} berada di luar rentang kelompok {$kelompokAcuan->name} ({$kelompokAcuan->tahun_mulai}-{$kelompokAcuan->tahun_selesai}), sehingga acuan kemungkinan besar tidak memiliki target untuk tahun ini.</li>";

        return new HtmlString(<<<HTML
            <ul class="list-disc ps-5 space-y-1">
                <li><strong>{$jumlahAcuan}</strong> acuan aktif pada kelompok <strong>{$kelompokAcuan->name}</strong> siap menjadi sumber penawaran.</li>
                <li>Target penawaran diambil dari target acuan tahun <strong>{$tahunTarget}</strong> (kolom Tahun pada {$tahunKerja->name}).</li>
                <li><strong>{$jumlahPenawaran}</strong> penawaran sudah terbentuk pada tahun kerja ini, <strong>{$jumlahBerpengajuan}</strong> di antaranya sudah memiliki pengajuan.</li>
                {$dalamKelompok}
            </ul>
            HTML);
    }

    /**
     * Daftar tahun kerja beserta statusnya saat ini.
     */
    protected function ringkasanTransisi(): Htmlable
    {
        $baris = TahunKerja::query()
            ->whereIn('status', [
                EnumStatusTahunKerja::Berjalan->value,
                EnumStatusTahunKerja::Perencanaan->value,
                EnumStatusTahunKerja::Penutupan->value,
            ])
            ->orderByDesc('tahun')
            ->get()
            ->map(fn (TahunKerja $tahunKerja): string => '<li><strong>'.e($tahunKerja->name).'</strong> — '
                .e($tahunKerja->status->getLabel()).'. '.e($tahunKerja->status->getDescription())
                .$this->stempelTransisi($tahunKerja).'</li>')
            ->implode('');

        if ($baris === '') {
            $baris = '<li class="text-gray-500">Belum ada tahun kerja yang berjalan, direncanakan, maupun dalam penutupan.</li>';
        }

        // Tombol transisi bergantung pada ada-tidaknya tahun kerja pada status
        // tertentu, jadi alasannya disebutkan di sini alih-alih membiarkan tombolnya
        // mati tanpa keterangan.
        if (TahunKerja::berjalan() === null) {
            $baris .= '<li class="text-warning-600"><strong>Akhiri Tahun Kerja Berjalan belum bisa dijalankan:</strong> belum ada tahun kerja pada slot Berjalan.</li>';
        }

        if (TahunKerja::berjalan() !== null && TahunKerja::penutupan()->isNotEmpty()) {
            $baris .= '<li class="text-gray-500"><strong>Batalkan Penutupan belum bisa dijalankan:</strong> slot Berjalan sedang terisi. '
                .'Pembatalan hanya mungkin selama tidak ada tahun kerja yang berjalan.</li>';
        }

        if (TahunKerja::perencanaan() === null) {
            $baris .= '<li class="text-warning-600"><strong>Mulai Tahun Perencanaan belum bisa dijalankan:</strong> belum ada tahun kerja pada slot Perencanaan. '
                .'Tetapkan dulu tahun kerja perencanaan di bagian di atas.</li>';
        }

        if (TahunKerja::penutupan()->isEmpty()) {
            $baris .= '<li class="text-warning-600"><strong>Kunci Tahun Kerja belum bisa dijalankan:</strong> belum ada tahun kerja berstatus Penutupan. '
                .'Status ini muncul setelah tahun berjalan diakhiri, baik lewat tombol Akhiri Tahun Kerja Berjalan maupun karena tahun perencanaan dijalankan.</li>';
        }

        return new HtmlString('<ul class="list-disc ps-5 space-y-1">'.$baris.'</ul>');
    }

    /**
     * Catatan kapan dan oleh siapa sebuah tahun kerja diakhiri atau dikunci.
     */
    protected function stempelTransisi(TahunKerja $tahunKerja): string
    {
        $stempel = [];

        if ($tahunKerja->ditutup_pada !== null) {
            $stempel[] = 'Diakhiri '.$tahunKerja->ditutup_pada->translatedFormat('d F Y H:i')
                .($tahunKerja->ditutupOleh?->name === null ? '' : ' oleh '.$tahunKerja->ditutupOleh->name);
        }

        if ($tahunKerja->dikunci_pada !== null) {
            $stempel[] = 'Dikunci '.$tahunKerja->dikunci_pada->translatedFormat('d F Y H:i')
                .($tahunKerja->dikunciOleh?->name === null ? '' : ' oleh '.$tahunKerja->dikunciOleh->name);
        }

        return $stempel === []
            ? ''
            : '<br><span class="text-gray-500">'.e(implode('. ', $stempel)).'.</span>';
    }

    /**
     * Dampak menjalankan tahun kerja yang sedang direncanakan.
     */
    protected function ringkasanMulai(): Htmlable
    {
        $perencanaan = TahunKerja::perencanaan();

        if ($perencanaan === null) {
            return new HtmlString('Belum ada tahun kerja yang direncanakan.');
        }

        $berjalan = TahunKerja::berjalan();

        $dampak = $berjalan === null
            ? ''
            : "<li>{$berjalan->name} berpindah ke status Penutupan: pengajuan dan realisasi barunya ditutup, tetapi realisasi yang sudah berjalan masih bisa dituntaskan.</li>"
                .(Setting::blokirTunggakanTahunLalu()
                    ? '<li>Unit kerja yang masih menyisakan realisasi belum tuntas di tahun tersebut belum boleh mengajukan realisasi tahun baru sampai tunggakannya selesai.</li>'
                    : '<li>Unit kerja yang masih menyisakan realisasi belum tuntas di tahun tersebut tetap boleh mengajukan realisasi tahun baru, sesuai Pengaturan Sistem.</li>');

        return new HtmlString(<<<HTML
            <ul class="list-disc ps-5 space-y-1">
                <li>{$perencanaan->name} menjadi tahun kerja berjalan, sehingga realisasi dan pencairannya terbuka.</li>
                {$dampak}
            </ul>
            HTML);
    }

    /**
     * Dampak mengakhiri tahun kerja yang sedang berjalan. Berbeda dengan
     * {@see ringkasanPenguncian()}, daftar ini tidak menghalangi: pengakhiran memang
     * boleh dilakukan selagi ada pekerjaan berjalan.
     */
    protected function ringkasanPengakhiran(): Htmlable
    {
        $tahunKerja = TahunKerja::berjalan();

        if ($tahunKerja === null) {
            return new HtmlString('Belum ada tahun kerja yang sedang berjalan.');
        }

        $dampak = collect(app(TransisiTahunKerja::class)->dampakPengakhiran($tahunKerja))
            ->map(fn (string $pesan): string => '<li>'.e($pesan).'</li>')
            ->implode('');

        $perencanaan = TahunKerja::perencanaan();

        $lanjutan = $perencanaan === null
            ? '<li class="text-warning-600">Belum ada tahun kerja pada slot Perencanaan, sehingga menu Pelaksanaan dan Verifikasi akan kosong sampai tahun kerja baru dijalankan.</li>'
            : "<li>{$perencanaan->name} sudah menunggu di slot Perencanaan dan bisa langsung dijalankan lewat tombol Mulai Tahun Perencanaan.</li>";

        return new HtmlString(<<<HTML
            <ul class="list-disc ps-5 space-y-1">
                <li>{$tahunKerja->name} berpindah ke status Penutupan: pengajuan dan realisasi barunya ditutup.</li>
                {$dampak}
                {$lanjutan}
                <li>Selama slot berjalan masih kosong, langkah ini bisa ditarik kembali lewat tombol Batalkan Penutupan.</li>
            </ul>
            HTML);
    }

    /**
     * Hasil pemeriksaan sebelum sebuah tahun kerja dikunci.
     */
    protected function ringkasanPenguncian(mixed $tahunKerjaId): Htmlable
    {
        $tahunKerja = TahunKerja::find($tahunKerjaId);

        if ($tahunKerja === null) {
            return new HtmlString('<span class="text-gray-500">Pilih tahun kerja untuk memeriksa kesiapannya.</span>');
        }

        $penghambat = app(TransisiTahunKerja::class)->penghambatPenguncian($tahunKerja);

        if ($penghambat === []) {
            return new HtmlString('<span class="text-success-600">Seluruh realisasi dan selisih anggaran tahun ini sudah tuntas. Tahun kerja aman dikunci.</span>');
        }

        $baris = collect($penghambat)
            ->map(fn (string $pesan): string => '<li>'.e($pesan).'</li>')
            ->implode('');

        return new HtmlString('<ul class="list-disc ps-5 space-y-1 text-danger-600">'.$baris.'</ul>');
    }

    /**
     * Penerapan yang akan menggeser tahun berjalan ke tahun lain. Pergantian tahun
     * kerja adalah keputusan tersendiri, bukan efek samping generate penawaran, jadi
     * jalurnya ditutup: akhiri dulu tahun yang berjalan lewat bagian Transisi.
     * Menerapkan ulang tahun yang sedang berjalan tetap terbuka karena tidak
     * memindahkan slot mana pun.
     */
    protected function menggeserTahunBerjalan(EnumStatusTahunKerja $slot): bool
    {
        if ($slot !== EnumStatusTahunKerja::Berjalan) {
            return false;
        }

        $pemegang = TahunKerja::berjalan();

        return $pemegang !== null && ! $pemegang->is($this->tahunKerjaTerpilih($slot));
    }

    protected function kelompokAcuanTerpilih(EnumStatusTahunKerja $slot): ?KelompokAcuan
    {
        return KelompokAcuan::find($this->data["{$slot->value}_kelompok_acuan_id"] ?? null);
    }

    protected function tahunKerjaTerpilih(EnumStatusTahunKerja $slot): ?TahunKerja
    {
        return TahunKerja::find($this->data["{$slot->value}_tahun_kerja_id"] ?? null);
    }

    /**
     * @return array{answer: int, text: string}
     */
    protected function buatCaptcha(): array
    {
        $firstNumber = random_int(1, 20);
        $secondNumber = random_int(1, 20);
        $operator = ['+', '-'][random_int(0, 1)];
        $answer = $operator === '+'
            ? $firstNumber + $secondNumber
            : $firstNumber - $secondNumber;

        return [
            'answer' => $answer,
            'text' => "{$firstNumber} {$operator} {$secondNumber} = ...",
        ];
    }
}
