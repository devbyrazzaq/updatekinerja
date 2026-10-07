<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\MonitoringProgramKerja;
use App\Filament\Widgets\Concerns\HasWidgetAuthorization;
use App\Filament\Widgets\Concerns\MembacaTahunBerjalan;
use App\Services\RingkasanMonitoring;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Kartu pembuka dashboard: pagu tahun kerja berjalan, seberapa besar yang sudah
 * terserap, sisanya, dan capaian program kerjanya.
 *
 * Angkanya memakai definisi Buku Anggaran — berbasis kas, jadi anggaran dianggap
 * terserap sejak benar-benar dicairkan. Kartu menautkan ke
 * {@see MonitoringProgramKerja} bagi pengguna yang boleh membukanya, karena
 * rinciannya per program kerja ada di sana.
 */
class RingkasanAnggaranWidget extends StatsOverviewWidget
{
    use HasWidgetAuthorization;
    use MembacaTahunBerjalan;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected array|int|null $columns = 2;

    protected function getHeading(): ?string
    {
        return 'Ringkasan Anggaran '.($this->tahunKerja()?->name ?? 'Tahun Kerja Berjalan');
    }

    protected function getDescription(): ?string
    {
        if ($this->tahunKerja() === null) {
            return 'Belum ada tahun kerja yang dijalankan. Tetapkan tahun kerja berjalan pada Pengaturan Program Kerja agar angka dashboard terisi.';
        }

        $jumlahUnit = count($this->monitoring()->namaUnitKerja());

        return 'Penyerapan anggaran '.$jumlahUnit.' unit kerja yang dapat Anda akses, dihitung sejak anggaran benar-benar dicairkan.';
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $ringkasan = $this->monitoring()->ringkasan();

        return [
            $this->statPagu($ringkasan),
            $this->statPenyerapan($ringkasan),
            $this->statSisa($ringkasan),
            $this->statCapaian($ringkasan),
        ];
    }

    protected function statPagu(RingkasanMonitoring $ringkasan): Stat
    {
        return Stat::make('Pagu Anggaran', $this->rupiah($ringkasan->pagu))
            ->description($ringkasan->jumlahProgram.' program kerja ditawarkan')
            ->descriptionIcon(Heroicon::OutlinedBanknotes)
            ->color($ringkasan->pagu > 0 ? 'primary' : 'gray')
            ->url($this->urlMonitoring());
    }

    protected function statPenyerapan(RingkasanMonitoring $ringkasan): Stat
    {
        $persentase = $ringkasan->persentasePenyerapan();

        return Stat::make('Anggaran Terserap', $this->rupiah($ringkasan->terserap()))
            ->description($persentase === null
                ? 'Pagu anggaran belum ditetapkan'
                : $this->persen($persentase).' dari pagu anggaran')
            ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
            ->color(match (true) {
                $persentase === null => 'gray',
                $persentase > 100 => 'danger',
                $persentase >= 75 => 'success',
                default => 'info',
            })
            ->url($this->urlMonitoring());
    }

    protected function statSisa(RingkasanMonitoring $ringkasan): Stat
    {
        return Stat::make('Sisa Pagu Anggaran', $this->rupiah($ringkasan->sisaPagu()))
            ->description($ringkasan->komitmen > 0
                ? $this->rupiah($ringkasan->komitmen).' sudah diajukan, menunggu cair'
                : 'Belum ada anggaran yang menunggu pencairan')
            ->descriptionIcon(Heroicon::OutlinedWallet)
            ->color($ringkasan->sisaPagu() < 0 ? 'danger' : 'warning')
            ->url($this->urlMonitoring());
    }

    protected function statCapaian(RingkasanMonitoring $ringkasan): Stat
    {
        $capaian = $ringkasan->capaian;

        return Stat::make('Capaian Program Kerja', $this->persen($capaian, 'Belum ada'))
            ->description($ringkasan->jumlahProgram > 0
                ? $ringkasan->jumlahProgramDiajukan.' dari '.$ringkasan->jumlahProgram.' program kerja dilaksanakan'
                : 'Program kerja belum ditawarkan')
            ->descriptionIcon(Heroicon::OutlinedClipboardDocumentCheck)
            ->color(match (true) {
                $capaian === null => 'gray',
                $capaian >= 80 => 'success',
                $capaian >= 50 => 'warning',
                default => 'danger',
            })
            ->url($this->urlMonitoring());
    }

    /**
     * Tautan ke halaman monitoring, hanya bagi pengguna yang boleh membukanya.
     */
    protected function urlMonitoring(): ?string
    {
        return MonitoringProgramKerja::canAccess() ? MonitoringProgramKerja::getUrl() : null;
    }
}
