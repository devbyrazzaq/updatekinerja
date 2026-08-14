<?php

namespace App\Filament\Resources\PaguAnggarans\Widgets;

use App\Services\KonteksProgramKerja;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * Ringkasan anggaran tahun kerja aktif: batas anggaran yang diberikan (diisi di
 * Pengaturan Program Kerja) dibandingkan dengan yang diajukan dan yang terpakai.
 */
class PaguAnggaranOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected array|int|null $columns = 2;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $tahunKerja = KonteksProgramKerja::tahunBerjalan();

        if ($tahunKerja === null) {
            return [
                Stat::make('Tahun Kerja', 'Belum diatur')
                    ->description('Tetapkan konteks di Pengaturan Program Kerja.')
                    ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                    ->color('warning'),
            ];
        }

        $batasAnggaran = (float) ($tahunKerja->batas_anggaran ?? 0);
        $totalPagu = $tahunKerja->totalPaguAnggaran();
        $totalPengajuan = $tahunKerja->totalPengajuanAnggaran();
        $totalDigunakan = $tahunKerja->totalAnggaranDigunakan();

        return [
            Stat::make('Batas Anggaran', $this->rupiah($batasAnggaran))
                ->description($batasAnggaran > 0
                    ? 'Nominal yang diberikan untuk tahun kerja ini'
                    : 'Belum diisi di Pengaturan Program Kerja')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color($batasAnggaran > 0 ? 'primary' : 'warning'),
            Stat::make('Total Pagu Saat Ini', $this->rupiah($totalPagu))
                ->description($this->bandingkan($totalPagu, $batasAnggaran, 'dari batas anggaran'))
                ->descriptionIcon(Heroicon::OutlinedCurrencyDollar)
                ->color($this->warna($totalPagu, $batasAnggaran)),
            Stat::make('Total Pengajuan Anggaran', $this->rupiah($totalPengajuan))
                ->description($this->bandingkan($totalPengajuan, $batasAnggaran, 'dari batas anggaran'))
                ->descriptionIcon(Heroicon::OutlinedDocumentText)
                ->color($this->warna($totalPengajuan, $batasAnggaran)),
            Stat::make('Anggaran Digunakan', $this->rupiah($totalDigunakan))
                ->description($this->bandingkan($totalDigunakan, $totalPengajuan, 'dari total pengajuan'))
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color($this->warna($totalDigunakan, $totalPengajuan)),
        ];
    }

    protected function rupiah(float $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }

    /**
     * Persentase nilai terhadap pembandingnya, mis. "42% dari batas anggaran".
     */
    protected function bandingkan(float $nilai, float $pembanding, string $keterangan): string
    {
        if ($pembanding <= 0) {
            return 'Belum ada pembanding';
        }

        return Number::percentage($nilai / $pembanding * 100, maxPrecision: 1, locale: 'id')." {$keterangan}";
    }

    /**
     * Merah bila nilai melampaui pembandingnya, kuning bila sudah mepet (di atas 90%).
     */
    protected function warna(float $nilai, float $pembanding): string
    {
        if ($pembanding <= 0) {
            return 'gray';
        }

        return match (true) {
            $nilai > $pembanding => 'danger',
            $nilai / $pembanding >= 0.9 => 'warning',
            default => 'success',
        };
    }
}
