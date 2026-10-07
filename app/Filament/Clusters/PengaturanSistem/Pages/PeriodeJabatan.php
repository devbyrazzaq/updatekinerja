<?php

namespace App\Filament\Clusters\PengaturanSistem\Pages;

use App\Models\KelompokAcuan;
use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class PeriodeJabatan extends HalamanPengaturan
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Periode Jabatan';

    protected static ?string $title = 'Periode Jabatan';

    protected static ?int $navigationSort = 40;

    /**
     * Metadata untuk form Role & Hak Akses (dibaca PermissionRegistrar).
     *
     * @var array<string, mixed>
     */
    public static array $permissions = [
        'heading' => 'Periode Jabatan',
        'description' => 'Hak akses untuk mengubah panjang periode jabatan yang dipakai acuan program kerja.',
        'permission_descriptions' => [
            'view_page_periode_jabatan' => 'Membuka halaman periode jabatan dan menyimpan perubahannya.',
        ],
    ];

    public function getSubheading(): string
    {
        return 'Panjang periode jabatan menentukan rentang tahun kelompok acuan dan kolom tahun pada acuan program kerja.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function nilaiTersimpan(): array
    {
        return [
            Setting::TAHUN_PER_PERIODE => Setting::tahunPerPeriode(),
        ];
    }

    /**
     * @return array<int, Section>
     */
    protected function isian(): array
    {
        return [
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
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function simpan(array $data): void
    {
        Setting::set(Setting::TAHUN_PER_PERIODE, (int) $data[Setting::TAHUN_PER_PERIODE]);
    }

    protected function ringkasanDampak(): string
    {
        $tahun = Setting::tahunPerPeriode();
        $kelompok = KelompokAcuan::active();

        $keterangan = "Kelompok acuan kini wajib mencakup {$tahun} tahun.";

        if ($kelompok !== null && count($kelompok->tahunList()) !== $tahun) {
            $keterangan .= " Kelompok aktif \"{$kelompok->name}\" ({$kelompok->tahun_mulai}-{$kelompok->tahun_selesai}) belum sesuai dan perlu disesuaikan.";
        }

        return $keterangan;
    }
}
