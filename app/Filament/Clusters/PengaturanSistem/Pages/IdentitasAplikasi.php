<?php

namespace App\Filament\Clusters\PengaturanSistem\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class IdentitasAplikasi extends HalamanPengaturan
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Identitas Aplikasi';

    protected static ?string $title = 'Identitas Aplikasi';

    protected static ?int $navigationSort = 10;

    /**
     * Metadata untuk form Role & Hak Akses (dibaca PermissionRegistrar).
     *
     * @var array<string, mixed>
     */
    public static array $permissions = [
        'heading' => 'Identitas Aplikasi',
        'description' => 'Hak akses untuk mengubah logo, nama aplikasi, dan nama instansi pada brand panel.',
        'permission_descriptions' => [
            'view_page_identitas_aplikasi' => 'Membuka halaman identitas aplikasi dan menyimpan perubahannya.',
        ],
    ];

    public function getSubheading(): string
    {
        return 'Logo dan tulisan di sini tampil sebagai brand di sidebar, topbar, dan judul tab peramban.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function nilaiTersimpan(): array
    {
        return [
            Setting::BRAND_NAMA => Setting::brandNama(),
            Setting::BRAND_INSTANSI => Setting::brandInstansi(),
            Setting::BRAND_LOGO => Setting::brandLogoPath(),
        ];
    }

    /**
     * @return array<int, Section>
     */
    protected function isian(): array
    {
        return [
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
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function simpan(array $data): void
    {
        Setting::set(Setting::BRAND_NAMA, trim((string) $data[Setting::BRAND_NAMA]));
        Setting::set(Setting::BRAND_INSTANSI, trim((string) $data[Setting::BRAND_INSTANSI]));
        Setting::set(Setting::BRAND_LOGO, (string) ($data[Setting::BRAND_LOGO] ?? ''));
    }

    protected function ringkasanDampak(): string
    {
        return 'Brand panel kini tampil sebagai '.Setting::brandNama().' — '.Setting::brandInstansi().'.';
    }
}
