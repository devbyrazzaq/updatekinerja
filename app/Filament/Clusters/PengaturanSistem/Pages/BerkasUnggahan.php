<?php

namespace App\Filament\Clusters\PengaturanSistem\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

class BerkasUnggahan extends HalamanPengaturan
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowUp;

    protected static ?string $navigationLabel = 'Berkas Unggahan';

    protected static ?string $title = 'Berkas Unggahan';

    protected static ?int $navigationSort = 60;

    /**
     * Metadata untuk form Role & Hak Akses (dibaca PermissionRegistrar).
     *
     * @var array<string, mixed>
     */
    public static array $permissions = [
        'heading' => 'Berkas Unggahan',
        'description' => 'Hak akses untuk mengubah batas jumlah dan ukuran berkas dokumen realisasi serta bukti pemasukan.',
        'permission_descriptions' => [
            'view_page_berkas_unggahan' => 'Membuka halaman berkas unggahan dan menyimpan perubahannya.',
        ],
    ];

    public function getSubheading(): string
    {
        return 'Batas jumlah dan ukuran berkas yang boleh diunggah pada dokumen realisasi dan bukti tanda terima pemasukan.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function nilaiTersimpan(): array
    {
        return [
            Setting::MAKS_PROPOSAL_REALISASI => Setting::maksProposalRealisasi(),
            Setting::MAKS_LAPORAN_REALISASI => Setting::maksLaporanRealisasi(),
            Setting::MAKS_UKURAN_PROPOSAL_MB => (int) Setting::get(Setting::MAKS_UKURAN_PROPOSAL_MB),
            Setting::MAKS_UKURAN_LAPORAN_MB => (int) Setting::get(Setting::MAKS_UKURAN_LAPORAN_MB),
            Setting::MAKS_BUKTI_PEMASUKAN => Setting::maksBuktiPemasukan(),
            Setting::MAKS_UKURAN_BUKTI_PEMASUKAN_MB => (int) Setting::get(Setting::MAKS_UKURAN_BUKTI_PEMASUKAN_MB),
        ];
    }

    /**
     * @return array<int, Section>
     */
    protected function isian(): array
    {
        return [
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
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function simpan(array $data): void
    {
        Setting::set(Setting::MAKS_PROPOSAL_REALISASI, (int) $data[Setting::MAKS_PROPOSAL_REALISASI]);
        Setting::set(Setting::MAKS_LAPORAN_REALISASI, (int) $data[Setting::MAKS_LAPORAN_REALISASI]);
        Setting::set(Setting::MAKS_UKURAN_PROPOSAL_MB, (int) $data[Setting::MAKS_UKURAN_PROPOSAL_MB]);
        Setting::set(Setting::MAKS_UKURAN_LAPORAN_MB, (int) $data[Setting::MAKS_UKURAN_LAPORAN_MB]);
        Setting::set(Setting::MAKS_BUKTI_PEMASUKAN, (int) $data[Setting::MAKS_BUKTI_PEMASUKAN]);
        Setting::set(Setting::MAKS_UKURAN_BUKTI_PEMASUKAN_MB, (int) $data[Setting::MAKS_UKURAN_BUKTI_PEMASUKAN_MB]);
    }

    protected function ringkasanDampak(): string
    {
        return 'Proposal maksimal '.Setting::maksProposalRealisasi().' berkas, laporan maksimal '.Setting::maksLaporanRealisasi()
            .' berkas, dan bukti pemasukan maksimal '.Setting::maksBuktiPemasukan().' berkas.';
    }
}
