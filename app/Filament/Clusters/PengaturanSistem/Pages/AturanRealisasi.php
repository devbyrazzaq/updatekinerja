<?php

namespace App\Filament\Clusters\PengaturanSistem\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class AturanRealisasi extends HalamanPengaturan
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Aturan Realisasi';

    protected static ?string $title = 'Aturan Realisasi';

    protected static ?int $navigationSort = 50;

    /**
     * Metadata untuk form Role & Hak Akses (dibaca PermissionRegistrar).
     *
     * @var array<string, mixed>
     */
    public static array $permissions = [
        'heading' => 'Aturan Realisasi',
        'description' => 'Hak akses untuk mengubah kuota realisasi berjalan dan aturan tunggakan tahun lalu.',
        'permission_descriptions' => [
            'view_page_aturan_realisasi' => 'Membuka halaman aturan realisasi dan menyimpan perubahannya.',
        ],
    ];

    public function getSubheading(): string
    {
        return 'Aturan main pengajuan realisasi program kerja oleh unit kerja: kuota yang boleh berjalan bersamaan dan sikap sistem terhadap tunggakan tahun lalu.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function nilaiTersimpan(): array
    {
        return [
            Setting::MAKS_REALISASI_BERJALAN => Setting::maksRealisasiBerjalan(),
            Setting::BLOKIR_TUNGGAKAN_TAHUN_LALU => Setting::blokirTunggakanTahunLalu(),
        ];
    }

    /**
     * @return array<int, Section>
     */
    protected function isian(): array
    {
        return [
            Section::make('Realisasi Program Kerja')
                ->description('Membatasi berapa banyak realisasi yang boleh berjalan bersamaan pada satu unit kerja, serta sikap sistem terhadap tunggakan realisasi tahun kerja sebelumnya.')
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
                    Toggle::make(Setting::BLOKIR_TUNGGAKAN_TAHUN_LALU)
                        ->label('Tahan Pengajuan Realisasi Baru Selama Ada Tunggakan Tahun Lalu')
                        ->helperText(new HtmlString(<<<'HTML'
                            Tunggakan adalah realisasi milik tahun kerja yang sudah tidak berjalan (mis. masih <strong>Menunggu Laporan Realisasi</strong> atau <strong>Verifikasi Laporan</strong>), yang dituntaskan lewat menu <strong>Pelaksanaan → Penyelesaian Tahun Lalu</strong>.
                            <br><strong>Aktif</strong>: unit kerja yang masih punya tunggakan <strong>tidak bisa</strong> mengajukan realisasi baru sampai seluruh tunggakannya tuntas.
                            <br><strong>Nonaktif</strong>: unit kerja <strong>tetap bisa</strong> mengajukan realisasi baru; tunggakannya hanya ditampilkan sebagai peringatan pada modal <em>Ajukan</em>. Tunggakan tetap memakai kuota realisasi berjalan di atas.
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
        Setting::set(Setting::MAKS_REALISASI_BERJALAN, (int) $data[Setting::MAKS_REALISASI_BERJALAN]);
        Setting::set(Setting::BLOKIR_TUNGGAKAN_TAHUN_LALU, (int) (bool) ($data[Setting::BLOKIR_TUNGGAKAN_TAHUN_LALU] ?? true));
    }

    protected function ringkasanDampak(): string
    {
        $maks = Setting::maksRealisasiBerjalan();

        return "Setiap unit kerja boleh menjalankan {$maks} realisasi bersamaan."
            .(Setting::blokirTunggakanTahunLalu()
                ? ' Unit kerja yang masih menunggak realisasi tahun lalu tidak bisa mengajukan realisasi baru.'
                : ' Unit kerja yang masih menunggak realisasi tahun lalu tetap bisa mengajukan realisasi baru.');
    }
}
