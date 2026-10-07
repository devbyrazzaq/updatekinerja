<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\BukuAnggaran;
use App\Filament\Pages\MonitoringProgramKerja;
use App\Filament\Pages\PengaturanProgramKerja;
use App\Filament\Pages\PenyelesaianTahunLalu;
use App\Filament\Pages\RingkasanUnitKerja;
use App\Filament\Resources\DaftarProgramKerjas\DaftarProgramKerjaResource;
use App\Filament\Resources\Dosens\DosenResource;
use App\Filament\Resources\JadwalPencairans\JadwalPencairanResource;
use App\Filament\Resources\PaguAnggarans\PaguAnggaranResource;
use App\Filament\Resources\Pemasukans\PemasukanResource;
use App\Filament\Resources\PenawaranProgramKerjas\PenawaranProgramKerjaResource;
use App\Filament\Resources\PengajuanProgramKerjas\PengajuanProgramKerjaResource;
use App\Filament\Resources\PerencanaanPengajuanProgramKerjas\PerencanaanPengajuanProgramKerjaResource;
use App\Filament\Resources\RealisasiProgramKerjas\RealisasiProgramKerjaResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\TenagaPendidiks\TenagaPendidikResource;
use App\Filament\Widgets\Concerns\HasWidgetAuthorization;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use UnitEnum;

/**
 * Pintasan ke menu yang paling sering dibuka, mengikuti alur kerja aplikasi: dari
 * penawaran dan pengajuan program kerja, realisasi dan pencairannya, sampai
 * pemantauan anggarannya.
 *
 * Pintasan hanya muncul untuk menu yang benar-benar boleh dibuka pengguna, sehingga
 * isinya berbeda-beda menurut role — sama seperti sidebar.
 */
class PintasanMenuWidget extends Widget
{
    use HasWidgetAuthorization;

    protected string $view = 'filament.widgets.pintasan-menu';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    /**
     * Menu yang dipintaskan beserta alasan singkat membukanya, urut mengikuti alur
     * kerja. Boleh berupa Resource maupun Page — keduanya menyediakan `canAccess()`,
     * `getUrl()`, dan metadata navigasi yang sama.
     *
     * @var array<class-string, string>
     */
    protected const PINTASAN = [
        DaftarProgramKerjaResource::class => 'Program kerja yang ditawarkan ke unit kerja.',
        PengajuanProgramKerjaResource::class => 'Ajukan program kerja beserta alokasi anggarannya.',
        RealisasiProgramKerjaResource::class => 'Ajukan dan laporkan pelaksanaan program kerja.',
        PemasukanResource::class => 'Catat pemasukan unit kerja beserta buktinya.',
        JadwalPencairanResource::class => 'Jadwal dan penandaan pencairan anggaran.',
        PaguAnggaranResource::class => 'Pembagian pagu anggaran tiap unit kerja.',
        PenawaranProgramKerjaResource::class => 'Susun penawaran program kerja tahun kerja.',
        PerencanaanPengajuanProgramKerjaResource::class => 'Pengajuan program kerja tahun yang direncanakan.',
        BukuAnggaran::class => 'Rincian pemasukan dan pengeluaran anggaran unit kerja.',
        MonitoringProgramKerja::class => 'Penyerapan anggaran dan capaian program kerja.',
        RingkasanUnitKerja::class => 'Rekap berdampingan seluruh unit kerja.',
        PenyelesaianTahunLalu::class => 'Tunggakan realisasi tahun kerja yang sudah ditutup.',
        DosenResource::class => 'Kelola akun dosen dan unit kerjanya.',
        TenagaPendidikResource::class => 'Kelola akun tenaga pendidik dan unit kerjanya.',
        RoleResource::class => 'Atur role beserta hak aksesnya.',
        PengaturanProgramKerja::class => 'Tetapkan tahun kerja berjalan dan tahun perencanaan.',
    ];

    /**
     * Pintasan yang boleh dibuka pengguna.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pintasan(): array
    {
        $pintasan = [];

        foreach (static::PINTASAN as $target => $keterangan) {
            // Menu yang sengaja disembunyikan dari sidebar — mis. menu perencanaan
            // selagi belum ada tahun yang direncanakan — ikut dilewati agar pintasan
            // tidak pernah mengantar ke halaman kosong.
            if (! $target::canAccess() || ! $target::shouldRegisterNavigation()) {
                continue;
            }

            $pintasan[] = [
                'label' => $target::getNavigationLabel(),
                'keterangan' => $keterangan,
                'grup' => $this->namaGrup($target),
                'url' => $target::getUrl(),
                'icon' => $target::getNavigationIcon() ?? Heroicon::OutlinedArrowRight,
            ];
        }

        return $pintasan;
    }

    /**
     * Nama grup navigasi menu, dipakai sebagai keterangan asal pintasan.
     *
     * @param  class-string  $target
     */
    protected function namaGrup(string $target): ?string
    {
        $grup = $target::getNavigationGroup();

        return $grup instanceof UnitEnum ? $grup->name : $grup;
    }
}
