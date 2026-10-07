<?php

namespace App\Filament\Clusters\PengaturanSistem\Pages;

use App\Enums\EnumJenisLatarMasuk;
use App\Filament\Actions\PulihkanHalamanMasukAction;
use App\Models\Setting;
use BackedEnum;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn as RepeaterTableColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class HalamanMasuk extends HalamanPengaturan
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowRightEndOnRectangle;

    protected static ?string $navigationLabel = 'Halaman Masuk';

    protected static ?string $title = 'Halaman Masuk';

    protected static ?int $navigationSort = 20;

    /**
     * Metadata untuk form Role & Hak Akses (dibaca PermissionRegistrar).
     *
     * @var array<string, mixed>
     */
    public static array $permissions = [
        'heading' => 'Halaman Masuk',
        'description' => 'Hak akses untuk mengubah tulisan, catatan akses, dan latar halaman masuk.',
        'permission_descriptions' => [
            'view_page_halaman_masuk' => 'Membuka pengaturan halaman masuk, menyimpan perubahannya, dan mengembalikannya ke bawaan.',
        ],
    ];

    public function getSubheading(): string
    {
        return 'Tulisan dan latar di sini tampil pada halaman masuk — layar pertama yang dilihat pengguna sebelum masuk ke panel.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function nilaiTersimpan(): array
    {
        return [
            Setting::MASUK_JUDUL => Setting::masukJudul(),
            Setting::MASUK_DESKRIPSI => Setting::masukDeskripsi(),
            Setting::MASUK_CATATAN_JUDUL => Setting::masukCatatanJudul(),
            Setting::MASUK_CATATAN => Setting::barisCatatanMasuk(),
            Setting::MASUK_LATAR_JENIS => Setting::masukLatarJenis()->value,
            Setting::MASUK_LATAR_GAMBAR => Setting::masukLatarGambarPath(),
            Setting::MASUK_LATAR_VIDEO => Setting::masukLatarVideoPath(),
            Setting::MASUK_LATAR_YOUTUBE => Setting::masukLatarYoutube(),
        ];
    }

    /**
     * @return array<int, Section>
     */
    protected function isian(): array
    {
        return [
            Section::make('Tulisan Halaman Masuk')
                ->description('Kalimat sambutan yang tampil besar di samping formulir masuk.')
                ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                ->columnSpanFull()
                ->schema([
                    TextInput::make(Setting::MASUK_JUDUL)
                        ->label('Judul')
                        ->required()
                        ->maxLength(120)
                        ->placeholder(Setting::DEFAULTS[Setting::MASUK_JUDUL])
                        ->helperText('Kalimat terbesar pada halaman masuk. Kosongkan lalu simpan untuk kembali ke tulisan bawaan.')
                        ->columnSpanFull(),
                    Textarea::make(Setting::MASUK_DESKRIPSI)
                        ->label('Deskripsi')
                        ->required()
                        ->maxLength(255)
                        ->rows(3)
                        ->placeholder(Setting::DEFAULTS[Setting::MASUK_DESKRIPSI])
                        ->helperText('Satu atau dua kalimat penjelas di bawah judul.')
                        ->columnSpanFull(),
                ]),
            Section::make('Catatan Akses')
                ->description('Butir petunjuk singkat di bawah formulir masuk, mis. cara memperoleh akun.')
                ->icon(Heroicon::OutlinedInformationCircle)
                ->columnSpanFull()
                ->schema([
                    TextInput::make(Setting::MASUK_CATATAN_JUDUL)
                        ->label('Judul Catatan')
                        ->required()
                        ->maxLength(50)
                        ->placeholder(Setting::DEFAULTS[Setting::MASUK_CATATAN_JUDUL])
                        ->columnSpanFull(),
                    Repeater::make(Setting::MASUK_CATATAN)
                        ->label('Butir Catatan')
                        ->table([
                            RepeaterTableColumn::make('Catatan'),
                        ])
                        ->schema([
                            TextInput::make('butir')
                                ->label('Catatan')
                                ->required()
                                ->maxLength(Setting::MASUK_CATATAN_MAKS_PANJANG)
                                ->placeholder('Mis. Hubungi admin jika akun belum aktif.'),
                        ])
                        ->addActionLabel('Tambah Catatan')
                        ->maxItems(Setting::MASUK_CATATAN_MAKS_BUTIR)
                        ->reorderable()
                        ->helperText('Maksimal '.Setting::MASUK_CATATAN_MAKS_BUTIR.' butir, masing-masing '.Setting::MASUK_CATATAN_MAKS_PANJANG.' karakter. Kosongkan seluruh butir bila blok catatan tidak perlu ditampilkan.')
                        ->columnSpanFull(),
                ]),
            Section::make('Latar Halaman Masuk')
                ->description('Gambar atau video yang mengisi seluruh panel brand halaman masuk.')
                ->icon(Heroicon::OutlinedPhoto)
                ->columnSpanFull()
                ->schema([
                    Radio::make(Setting::MASUK_LATAR_JENIS)
                        ->label('Jenis Latar')
                        ->options(EnumJenisLatarMasuk::class)
                        ->required()
                        ->live()
                        ->default(EnumJenisLatarMasuk::Gambar->value)
                        ->helperText('Video dan YouTube diputar otomatis tanpa suara dan berulang saat halaman masuk dibuka.')
                        ->columnSpanFull(),
                    FileUpload::make(Setting::MASUK_LATAR_GAMBAR)
                        ->label('Gambar Latar')
                        ->image()
                        ->disk(Setting::MASUK_LATAR_DISK)
                        ->visibility('public')
                        ->directory('halaman-masuk')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->maxSize(4096)
                        ->imagePreviewHeight('160')
                        ->visible(fn (Get $get): bool => static::jenisLatarTerpilih($get) === EnumJenisLatarMasuk::Gambar)
                        ->helperText('Format PNG, JPG, atau WEBP maksimal 4MB. Kosongkan untuk memakai gambar bawaan sistem — gambar melebar penuh, jadi pakai foto memanjang beresolusi tinggi.')
                        ->columnSpanFull(),
                    FileUpload::make(Setting::MASUK_LATAR_VIDEO)
                        ->label('Berkas Video')
                        ->disk(Setting::MASUK_LATAR_DISK)
                        ->visibility('public')
                        ->directory('halaman-masuk')
                        ->acceptedFileTypes(['video/mp4', 'video/webm'])
                        ->maxSize(51200)
                        ->visible(fn (Get $get): bool => static::jenisLatarTerpilih($get) === EnumJenisLatarMasuk::Video)
                        ->helperText(new HtmlString(<<<'HTML'
                            Format MP4 atau WEBM maksimal 50MB. Video diputar tanpa suara dan berulang.
                            <br>Pakai video pendek (10–30 detik): berkasnya ikut diunduh peramban setiap halaman masuk dibuka.
                            <br>Selama berkasnya belum diunggah, latar tetap memakai gambar.
                            HTML))
                        ->columnSpanFull(),
                    TextInput::make(Setting::MASUK_LATAR_YOUTUBE)
                        ->label('Tautan YouTube')
                        ->url()
                        ->maxLength(255)
                        ->placeholder('https://www.youtube.com/watch?v=xxxxxxxxxxx')
                        ->visible(fn (Get $get): bool => static::jenisLatarTerpilih($get) === EnumJenisLatarMasuk::Youtube)
                        ->required(fn (Get $get): bool => static::jenisLatarTerpilih($get) === EnumJenisLatarMasuk::Youtube)
                        // Yang disematkan adalah id videonya, jadi tautan yang tidak bisa
                        // diurai ditolak di sini — kalau lolos, latarnya diam-diam turun
                        // ke gambar dan pengguna mengira pengaturannya tidak tersimpan.
                        ->rule(static function (): Closure {
                            return static function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && Setting::uraiYoutubeId(is_string($value) ? $value : null) === null) {
                                    $fail('Tautan YouTube tidak dikenali. Salin tautan videonya, mis. https://www.youtube.com/watch?v=xxxxxxxxxxx.');
                                }
                            };
                        })
                        ->helperText('Tautan video biasa, youtu.be, Shorts, atau siaran langsung. Video diputar tanpa suara, berulang, dan tanpa kendali pemutar.')
                        ->columnSpanFull(),
                    Actions::make([
                        PulihkanHalamanMasukAction::make(),
                    ])
                        ->key('aksiHalamanMasuk')
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function simpan(array $data): void
    {
        Setting::set(Setting::MASUK_JUDUL, trim((string) $data[Setting::MASUK_JUDUL]));
        Setting::set(Setting::MASUK_DESKRIPSI, trim((string) $data[Setting::MASUK_DESKRIPSI]));
        Setting::set(Setting::MASUK_CATATAN_JUDUL, trim((string) $data[Setting::MASUK_CATATAN_JUDUL]));
        Setting::set(Setting::MASUK_CATATAN, Setting::rangkaiCatatanMasuk($data[Setting::MASUK_CATATAN] ?? []));
        Setting::set(Setting::MASUK_LATAR_JENIS, static::nilaiJenisLatar($data[Setting::MASUK_LATAR_JENIS] ?? null));
        Setting::set(Setting::MASUK_LATAR_GAMBAR, (string) ($data[Setting::MASUK_LATAR_GAMBAR] ?? ''));
        Setting::set(Setting::MASUK_LATAR_VIDEO, (string) ($data[Setting::MASUK_LATAR_VIDEO] ?? ''));
        Setting::set(Setting::MASUK_LATAR_YOUTUBE, trim((string) ($data[Setting::MASUK_LATAR_YOUTUBE] ?? '')));
    }

    protected function ringkasanDampak(): string
    {
        return 'Halaman masuk kini memakai latar '.str(Setting::masukLatarJenisTerpasang()->getLabel())->lower().'.';
    }

    /**
     * Jenis latar yang sedang dipilih di layar. Radio berisi opsi enum mengembalikan
     * nilai string, tetapi state yang baru diisi ulang bisa berupa enum-nya.
     */
    protected static function jenisLatarTerpilih(Get $get): EnumJenisLatarMasuk
    {
        return static::sebagaiJenisLatar($get(Setting::MASUK_LATAR_JENIS));
    }

    /**
     * Nilai jenis latar yang siap disimpan sebagai pengaturan.
     */
    protected static function nilaiJenisLatar(mixed $jenis): string
    {
        return static::sebagaiJenisLatar($jenis)->value;
    }

    protected static function sebagaiJenisLatar(mixed $jenis): EnumJenisLatarMasuk
    {
        if ($jenis instanceof EnumJenisLatarMasuk) {
            return $jenis;
        }

        return EnumJenisLatarMasuk::tryFrom((string) $jenis) ?? EnumJenisLatarMasuk::Gambar;
    }
}
