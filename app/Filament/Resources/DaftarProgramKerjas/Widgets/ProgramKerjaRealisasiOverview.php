<?php

namespace App\Filament\Resources\DaftarProgramKerjas\Widgets;

use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\RealisasiProgramKerja;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ringkasan capaian satu program kerja: akumulasi anggaran yang dialokasikan
 * lewat pengajuan, penyerapannya lewat realisasi, dan persentase ketercapaian.
 * Baris pertama menyorot sisi pengajuan/alokasi, baris kedua sisi realisasi.
 */
class ProgramKerjaRealisasiOverview extends StatsOverviewWidget
{
    public PenawaranProgramKerja $record;

    protected ?string $pollingInterval = null;

    protected array|int|null $columns = 3;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $pengajuanQuery = $this->pengajuanQuery();
        $realisasiQuery = $this->realisasiQuery();

        $jumlahPengajuan = (clone $pengajuanQuery)->count();
        $totalDialokasikan = (float) (clone $pengajuanQuery)->sum('alokasi_anggaran');

        $jumlahRealisasi = (clone $realisasiQuery)->count();
        $totalDigunakan = (float) (clone $realisasiQuery)->sum('anggaran_digunakan');

        $persentaseAnggaran = $totalDialokasikan > 0
            ? (int) round($totalDigunakan / $totalDialokasikan * 100)
            : 0;

        $rataKetercapaian = $this->rataKetercapaianPengajuan();

        return [
            // Baris 1 — Anggaran
            Stat::make('Total Anggaran Dialokasikan', $this->rupiah($totalDialokasikan))
                ->description('Akumulasi alokasi seluruh pengajuan')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('primary'),
            Stat::make('Total Anggaran Digunakan', $this->rupiah($totalDigunakan))
                ->description('Akumulasi anggaran realisasi terpakai')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color($totalDigunakan > 0 ? 'success' : 'gray'),
            Stat::make('Penyerapan Anggaran', $persentaseAnggaran.'%')
                ->description('Anggaran digunakan terhadap dialokasikan')
                ->descriptionIcon(Heroicon::OutlinedChartPie)
                ->color($this->persenColor($persentaseAnggaran)),

            // Baris 2 — Jumlah & ketercapaian
            Stat::make('Jumlah Pengajuan', (string) $jumlahPengajuan)
                ->description('Pengajuan yang dibuat untuk program kerja ini')
                ->descriptionIcon(Heroicon::OutlinedPaperAirplane)
                ->color('info'),
            Stat::make('Jumlah Realisasi', (string) $jumlahRealisasi)
                ->description('Realisasi yang terhubung ke pengajuan')
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('info'),
            Stat::make('Persentase Ketercapaian', $rataKetercapaian.'%')
                ->description('Rata-rata ketercapaian seluruh pengajuan')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color($this->persenColor($rataKetercapaian)),
        ];
    }

    /**
     * Seluruh pengajuan milik program kerja ini.
     *
     * @return Builder<PengajuanProgramKerja>
     */
    protected function pengajuanQuery(): Builder
    {
        return PengajuanProgramKerja::query()
            ->where('penawaran_program_kerja_id', $this->record->getKey());
    }

    /**
     * Seluruh realisasi yang terikat ke pengajuan program kerja ini.
     *
     * @return Builder<RealisasiProgramKerja>
     */
    protected function realisasiQuery(): Builder
    {
        return RealisasiProgramKerja::query()
            ->whereHas('pengajuanProgramKerja', fn (Builder $query): Builder => $query
                ->where('penawaran_program_kerja_id', $this->record->getKey()));
    }

    /**
     * Rata-rata ketercapaian seluruh pengajuan program kerja ini. Ketercapaian tiap
     * pengajuan mengikuti realisasi terakhirnya; pengajuan tanpa realisasi dihitung 0.
     */
    protected function rataKetercapaianPengajuan(): int
    {
        $ketercapaian = $this->record
            ->pengajuanProgramKerjas()
            ->with('realisasiProgramKerjas')
            ->get()
            ->map(fn (PengajuanProgramKerja $pengajuan): int => $pengajuan->persentaseKetercapaian());

        return $ketercapaian->isEmpty()
            ? 0
            : (int) round((float) $ketercapaian->avg());
    }

    protected function persenColor(int $persen): string
    {
        return match (true) {
            $persen >= 75 => 'success',
            $persen >= 40 => 'warning',
            default => 'danger',
        };
    }

    protected function rupiah(float $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }
}
