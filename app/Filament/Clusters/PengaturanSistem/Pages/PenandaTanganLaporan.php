<?php

namespace App\Filament\Clusters\PengaturanSistem\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class PenandaTanganLaporan extends HalamanPengaturan
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $navigationLabel = 'Penanda Tangan Laporan';

    protected static ?string $title = 'Penanda Tangan Laporan';

    protected static ?int $navigationSort = 30;

    /**
     * Metadata untuk form Role & Hak Akses (dibaca PermissionRegistrar).
     *
     * @var array<string, mixed>
     */
    public static array $permissions = [
        'heading' => 'Penanda Tangan Laporan',
        'description' => 'Hak akses untuk mengubah identitas penanda tangan pada laporan PDF.',
        'permission_descriptions' => [
            'view_page_penanda_tangan_laporan' => 'Membuka halaman penanda tangan laporan dan menyimpan perubahannya.',
        ],
    ];

    public function getSubheading(): string
    {
        return 'Identitas di sini tercetak pada blok tanda tangan setiap laporan PDF.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function nilaiTersimpan(): array
    {
        return [
            Setting::PENANDATANGAN_JABATAN => Setting::penandatanganJabatan(),
            Setting::PENANDATANGAN_NAMA => Setting::penandatanganNama(),
            Setting::PENANDATANGAN_NOMOR => Setting::penandatanganNomor(),
            Setting::PENANDATANGAN_KOTA => Setting::penandatanganKota(),
        ];
    }

    /**
     * @return array<int, Section>
     */
    protected function isian(): array
    {
        return [
            Section::make('Penanda Tangan Laporan')
                ->description('Identitas pimpinan yang tercetak pada blok tanda tangan laporan PDF, mis. Laporan Pencairan.')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make(Setting::PENANDATANGAN_JABATAN)
                        ->label('Jabatan Pimpinan')
                        ->maxLength(100)
                        ->placeholder('Kepala Biro Keuangan')
                        ->helperText('Tercetak di baris paling atas blok tanda tangan, di atas nama.'),
                    TextInput::make(Setting::PENANDATANGAN_NAMA)
                        ->label('Nama Pimpinan')
                        ->maxLength(150)
                        ->placeholder('Dr. Hj. Siti Aminah, S.E., M.M.')
                        ->helperText('Tulis lengkap dengan gelar depan dan belakang. Tercetak bergaris bawah di bawah jabatan.'),
                    TextInput::make(Setting::PENANDATANGAN_NOMOR)
                        ->label('Nomor Karyawan Pimpinan')
                        ->maxLength(50)
                        ->placeholder('NIK 198701012015041002')
                        ->helperText('Tercetak di bawah nama yang bergaris bawah.'),
                    TextInput::make(Setting::PENANDATANGAN_KOTA)
                        ->label('Kota pada Baris Tanggal')
                        ->maxLength(50)
                        ->placeholder('Lamongan')
                        ->helperText(new HtmlString(<<<'HTML'
                            Mendahului tanggal, mis. <strong>Lamongan, 17 Agustus 2026</strong>. Kosongkan bila cukup tanggalnya saja.
                            <br><strong>Tanggalnya sendiri dipilih saat mengunduh laporan</strong>, bukan di sini.
                            HTML)),
                ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function simpan(array $data): void
    {
        Setting::set(Setting::PENANDATANGAN_JABATAN, trim((string) ($data[Setting::PENANDATANGAN_JABATAN] ?? '')));
        Setting::set(Setting::PENANDATANGAN_NAMA, trim((string) ($data[Setting::PENANDATANGAN_NAMA] ?? '')));
        Setting::set(Setting::PENANDATANGAN_NOMOR, trim((string) ($data[Setting::PENANDATANGAN_NOMOR] ?? '')));
        Setting::set(Setting::PENANDATANGAN_KOTA, trim((string) ($data[Setting::PENANDATANGAN_KOTA] ?? '')));
    }

    protected function ringkasanDampak(): string
    {
        $nama = Setting::penandatanganNama();

        return $nama === ''
            ? 'Blok tanda tangan laporan memakai nama pengguna yang mengunduhnya.'
            : "Blok tanda tangan laporan kini atas nama {$nama}.";
    }
}
