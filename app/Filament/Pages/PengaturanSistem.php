<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasPageAuthorization;
use App\Models\KelompokAcuan;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class PengaturanSistem extends Page
{
    use HasPageAuthorization;

    protected string $view = 'filament.pages.pengaturan-sistem';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Pengaturan Sistem';

    protected static ?string $title = 'Pengaturan Sistem';

    /**
     * Metadata untuk form Role & Hak Akses (dibaca PermissionRegistrar).
     *
     * @var array<string, mixed>
     */
    public static array $permissions = [
        'heading' => 'Pengaturan Sistem',
        'description' => 'Hak akses untuk membuka dan mengubah pengaturan perilaku sistem.',
        'permission_descriptions' => [
            'view_page_pengaturan_sistem' => 'Membuka halaman pengaturan sistem dan menyimpan perubahannya.',
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
        return 'Pengaturan di halaman ini mengubah aturan main yang dipakai menu lain: identitas aplikasi pada brand panel, batas rentang tahun kelompok acuan, kuota realisasi yang boleh berjalan bersamaan, serta batas berkas dokumen realisasi dan bukti tanda terima pemasukan.';
    }

    public function mount(): void
    {
        $this->form->fill([
            Setting::BRAND_NAMA => Setting::brandNama(),
            Setting::BRAND_INSTANSI => Setting::brandInstansi(),
            Setting::BRAND_LOGO => Setting::brandLogoPath(),
            Setting::TAHUN_PER_PERIODE => Setting::tahunPerPeriode(),
            Setting::MAKS_REALISASI_BERJALAN => Setting::maksRealisasiBerjalan(),
            Setting::MAKS_PROPOSAL_REALISASI => Setting::maksProposalRealisasi(),
            Setting::MAKS_LAPORAN_REALISASI => Setting::maksLaporanRealisasi(),
            Setting::MAKS_UKURAN_PROPOSAL_MB => (int) Setting::get(Setting::MAKS_UKURAN_PROPOSAL_MB),
            Setting::MAKS_UKURAN_LAPORAN_MB => (int) Setting::get(Setting::MAKS_UKURAN_LAPORAN_MB),
            Setting::MAKS_BUKTI_PEMASUKAN => Setting::maksBuktiPemasukan(),
            Setting::MAKS_UKURAN_BUKTI_PEMASUKAN_MB => (int) Setting::get(Setting::MAKS_UKURAN_BUKTI_PEMASUKAN_MB),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Identitas Aplikasi')
                        ->description('Menentukan logo dan tulisan yang tampil sebagai brand di sidebar serta topbar panel.')
                        ->icon(Heroicon::OutlinedSparkles)
                        ->columnSpanFull()
                        ->columns(2)
                        ->schema([
                            FileUpload::make(Setting::BRAND_LOGO)
                                ->label('Logo')
                                ->image()
                                ->disk(Setting::BRAND_LOGO_DISK)
                                ->visibility('public')
                                ->directory('brand')
                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'])
                                ->maxSize(2048)
                                ->helperText('Tampil di sebelah kiri tulisan brand. Kosongkan bila brand cukup berupa tulisan saja. Format PNG, JPG, SVG, atau WEBP maksimal 2MB — gunakan gambar berlatar transparan agar rapi pada mode gelap.')
                                ->columnSpanFull(),
                            TextInput::make(Setting::BRAND_NAMA)
                                ->label('Nama Aplikasi')
                                ->required()
                                ->maxLength(50)
                                ->placeholder(Setting::DEFAULTS[Setting::BRAND_NAMA])
                                ->helperText('Baris pertama brand, sekaligus judul tab peramban.'),
                            TextInput::make(Setting::BRAND_INSTANSI)
                                ->label('Nama Instansi')
                                ->required()
                                ->maxLength(100)
                                ->placeholder(Setting::DEFAULTS[Setting::BRAND_INSTANSI])
                                ->helperText(new HtmlString(<<<'HTML'
                                    Baris kedua brand untuk pengguna berakses penuh yang melihat <strong>seluruh unit kerja</strong> sekaligus.
                                    <br>Pengguna yang datanya dibatasi melihat <strong>nama unit kerja aktif</strong> miliknya di baris ini, bukan nama instansi.
                                    HTML)),
                        ]),
                    Section::make('Periode Jabatan & Acuan Program Kerja')
                        ->description('Menentukan panjang satu periode jabatan dalam tahun. Dipakai sebagai aturan validasi dan penentu kolom tahun pada acuan program kerja.')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->columnSpanFull()
                        ->schema([
                            TextInput::make(Setting::TAHUN_PER_PERIODE)
                                ->label('Jumlah Tahun dalam 1 Periode Jabatan')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(20)
                                ->suffix('tahun')
                                ->helperText(new HtmlString(<<<'HTML'
                                    Dihitung <strong>inklusif</strong>: bila diisi <strong>5</strong>, kelompok acuan yang mulai 2025 wajib selesai di 2029.
                                    <br>Berpengaruh pada:
                                    <ul class="list-disc ps-5 mt-1">
                                        <li><strong>Program Kerja → Kelompok Acuan</strong>: validasi Tahun Mulai & Tahun Selesai saat menyimpan (tahun selesai terisi otomatis).</li>
                                        <li><strong>Program Kerja → Acuan Program Kerja</strong>: jumlah kolom tahun pada tabel dan jumlah baris target (nilai + satuan) pada formulir.</li>
                                    </ul>
                                    HTML))
                                ->columnSpanFull(),
                        ]),
                    Section::make('Realisasi Program Kerja')
                        ->description('Membatasi berapa banyak realisasi yang boleh berjalan bersamaan pada satu unit kerja.')
                        ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                        ->columnSpanFull()
                        ->schema([
                            TextInput::make(Setting::MAKS_REALISASI_BERJALAN)
                                ->label('Jumlah Realisasi yang Boleh Berjalan Bersamaan')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(50)
                                ->suffix('realisasi')
                                ->helperText(new HtmlString(<<<'HTML'
                                    Dihitung <strong>per unit kerja</strong>. Bila diisi <strong>2</strong>, satu unit kerja boleh mengajukan 2 realisasi sekaligus; kuota baru terbuka lagi setelah salah satunya <strong>Selesai</strong> (atau <strong>Ditolak</strong>).
                                    <br>Realisasi berstatus <strong>Draf</strong> belum memakai kuota — kuota mulai terpakai saat realisasi <strong>Diajukan</strong> dan tetap terpakai selama proses verifikasi, penjadwalan, hingga verifikasi laporan.
                                    <br>Berpengaruh pada:
                                    <ul class="list-disc ps-5 mt-1">
                                        <li><strong>Pelaksanaan → Realisasi Program Kerja</strong>: tombol <em>Ajukan</em> ditolak bila kuota unit kerja sudah penuh.</li>
                                    </ul>
                                    HTML))
                                ->columnSpanFull(),
                        ]),
                    Section::make('Dokumen Realisasi')
                        ->description('Membatasi berapa banyak berkas proposal dan laporan yang boleh diunggah pada satu realisasi program kerja.')
                        ->icon(Heroicon::OutlinedDocumentArrowUp)
                        ->columnSpanFull()
                        ->columns(2)
                        ->schema([
                            TextInput::make(Setting::MAKS_PROPOSAL_REALISASI)
                                ->label('Maksimal Berkas Proposal')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(20)
                                ->suffix('berkas')
                                ->helperText('Batas jumlah berkas proposal per realisasi. Minimal unggahan tetap 1 berkas.'),
                            TextInput::make(Setting::MAKS_LAPORAN_REALISASI)
                                ->label('Maksimal Berkas Laporan')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(20)
                                ->suffix('berkas')
                                ->helperText('Batas jumlah berkas laporan per realisasi. Minimal unggahan tetap 1 berkas.'),
                            TextInput::make(Setting::MAKS_UKURAN_PROPOSAL_MB)
                                ->label('Maksimal Ukuran Berkas Proposal')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(100)
                                ->suffix('MB')
                                ->helperText('Batas ukuran tiap berkas proposal yang diunggah.'),
                            TextInput::make(Setting::MAKS_UKURAN_LAPORAN_MB)
                                ->label('Maksimal Ukuran Berkas Laporan')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(100)
                                ->suffix('MB')
                                ->helperText('Batas ukuran tiap berkas laporan yang diunggah.'),
                        ]),
                    Section::make('Bukti Tanda Terima Pemasukan')
                        ->description('Membatasi berkas bukti tanda terima yang diunggah unit kerja untuk mengesahkan pemasukan.')
                        ->icon(Heroicon::OutlinedBanknotes)
                        ->columnSpanFull()
                        ->columns(2)
                        ->schema([
                            TextInput::make(Setting::MAKS_BUKTI_PEMASUKAN)
                                ->label('Maksimal Berkas Bukti')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(20)
                                ->suffix('berkas')
                                ->helperText('Batas jumlah berkas bukti per pemasukan. Minimal unggahan tetap 1 berkas.'),
                            TextInput::make(Setting::MAKS_UKURAN_BUKTI_PEMASUKAN_MB)
                                ->label('Maksimal Ukuran Berkas Bukti')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(100)
                                ->suffix('MB')
                                ->helperText('Batas ukuran tiap berkas bukti yang diunggah.'),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Simpan Pengaturan')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set(Setting::BRAND_NAMA, trim((string) $data[Setting::BRAND_NAMA]));
        Setting::set(Setting::BRAND_INSTANSI, trim((string) $data[Setting::BRAND_INSTANSI]));
        Setting::set(Setting::BRAND_LOGO, (string) ($data[Setting::BRAND_LOGO] ?? ''));
        Setting::set(Setting::TAHUN_PER_PERIODE, (int) $data[Setting::TAHUN_PER_PERIODE]);
        Setting::set(Setting::MAKS_REALISASI_BERJALAN, (int) $data[Setting::MAKS_REALISASI_BERJALAN]);
        Setting::set(Setting::MAKS_PROPOSAL_REALISASI, (int) $data[Setting::MAKS_PROPOSAL_REALISASI]);
        Setting::set(Setting::MAKS_LAPORAN_REALISASI, (int) $data[Setting::MAKS_LAPORAN_REALISASI]);
        Setting::set(Setting::MAKS_UKURAN_PROPOSAL_MB, (int) $data[Setting::MAKS_UKURAN_PROPOSAL_MB]);
        Setting::set(Setting::MAKS_UKURAN_LAPORAN_MB, (int) $data[Setting::MAKS_UKURAN_LAPORAN_MB]);
        Setting::set(Setting::MAKS_BUKTI_PEMASUKAN, (int) $data[Setting::MAKS_BUKTI_PEMASUKAN]);
        Setting::set(Setting::MAKS_UKURAN_BUKTI_PEMASUKAN_MB, (int) $data[Setting::MAKS_UKURAN_BUKTI_PEMASUKAN_MB]);

        Notification::make()
            ->title('Pengaturan sistem berhasil disimpan')
            ->body($this->ringkasanDampak())
            ->success()
            ->send();
    }

    /**
     * Keterangan singkat dampak pengaturan setelah disimpan.
     */
    protected function ringkasanDampak(): string
    {
        $tahun = Setting::tahunPerPeriode();
        $maks = Setting::maksRealisasiBerjalan();
        $kelompok = KelompokAcuan::active();

        $keterangan = "Kelompok acuan kini wajib mencakup {$tahun} tahun, dan setiap unit kerja boleh menjalankan {$maks} realisasi bersamaan.";

        if ($kelompok !== null && count($kelompok->tahunList()) !== $tahun) {
            $keterangan .= " Kelompok aktif \"{$kelompok->name}\" ({$kelompok->tahun_mulai}-{$kelompok->tahun_selesai}) belum sesuai dan perlu disesuaikan.";
        }

        return $keterangan;
    }
}
