<?php

namespace App\Filament\Resources\RealisasiProgramKerjas\Widgets;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Models\PengajuanProgramKerja;
use App\Models\RealisasiProgramKerja;
use App\Services\KonteksProgramKerja;
use App\Services\PermissionRegistrar;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Livewire\Attributes\Reactive;

/**
 * Ringkasan Realisasi Program Kerja mengikuti unit kerja yang dipilih di halaman:
 * anggaran pengajuan yang disetujui, yang sudah digunakan realisasi, sisanya, serta
 * jumlah realisasi yang diajukan. Semua angka dibatasi tahun kerja aktif.
 */
class RealisasiProgramKerjaOverview extends StatsOverviewWidget
{
    /**
     * Unit kerja terpilih dari halaman induk. Null berarti semua unit yang boleh diakses.
     */
    #[Reactive]
    public ?int $unitKerjaId = null;

    protected ?string $pollingInterval = null;

    protected array|int|null $columns = 2;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $unitIds = $this->scopedUnitIds();
        $diajukan = $this->totalAnggaranDiajukan($unitIds);
        $disetujui = $this->totalAnggaranDisetujui($unitIds);
        $digunakan = $this->totalDigunakan($unitIds);
        $sisa = $disetujui - $digunakan;
        $realisasi = $this->totalRealisasiDiajukan($unitIds);
        $selesai = $this->totalRealisasiSelesai($unitIds);

        return [
            Stat::make('Anggaran Disetujui', $this->rupiah($disetujui))
                ->description($this->bandingkan($disetujui, $diajukan, 'dari anggaran yang diajukan'))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make('Sudah Digunakan', $this->rupiah($digunakan))
                ->description($this->bandingkan($digunakan, $disetujui, 'dari anggaran disetujui'))
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
                ->color($this->warna($digunakan, $disetujui)),
            Stat::make('Sisa Anggaran', $this->rupiah($sisa))
                ->description($sisa < 0
                    ? $this->bandingkan(abs($sisa), $disetujui, 'melebihi anggaran disetujui')
                    : $this->bandingkan($sisa, $disetujui, 'dari anggaran disetujui belum terpakai'))
                ->descriptionIcon(Heroicon::OutlinedWallet)
                ->color($sisa < 0 ? 'danger' : 'info'),
            Stat::make('Realisasi Diajukan', (string) $realisasi)
                ->description($this->bandingkan($selesai, $realisasi, 'sudah selesai'))
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('gray'),
        ];
    }

    /**
     * Persentase nilai terhadap pembandingnya, mis. "42% dari anggaran disetujui".
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

    /**
     * Id unit kerja yang menjadi dasar perhitungan. Null berarti seluruh unit.
     *
     * @return array<int, int>|null
     */
    protected function scopedUnitIds(): ?array
    {
        if ($this->unitKerjaId !== null) {
            return [$this->unitKerjaId];
        }

        $user = auth()->user();

        if ($user === null || $user->isPrivileged()) {
            return null;
        }

        return PermissionRegistrar::permittedUnitIds($user)->all();
    }

    /**
     * Total alokasi pengajuan berstatus diterima pada tahun kerja aktif.
     *
     * @param  array<int, int>|null  $unitIds
     */
    protected function totalAnggaranDisetujui(?array $unitIds): float
    {
        return (float) $this->pengajuanQuery($unitIds)
            ->where('status', EnumStatusPengajuan::Diterima)
            ->sum('alokasi_anggaran');
    }

    /**
     * Total alokasi seluruh pengajuan yang sudah dikirim (di luar draf), menjadi
     * pembanding berapa besar yang akhirnya disetujui.
     *
     * @param  array<int, int>|null  $unitIds
     */
    protected function totalAnggaranDiajukan(?array $unitIds): float
    {
        return (float) $this->pengajuanQuery($unitIds)
            ->where('status', '!=', EnumStatusPengajuan::Draft->value)
            ->sum('alokasi_anggaran');
    }

    /**
     * Kueri dasar pengajuan yang sudah dibatasi tahun kerja aktif dan unit terpilih.
     *
     * @param  array<int, int>|null  $unitIds
     */
    protected function pengajuanQuery(?array $unitIds): Builder
    {
        $query = KonteksProgramKerja::applyPelaksanaanVia(
            PengajuanProgramKerja::query(),
            'penawaranProgramKerja',
        );

        if ($unitIds !== null) {
            $query->whereIn('unit_kerja_id', $unitIds);
        }

        return $query;
    }

    /**
     * Total anggaran yang sudah dipakai realisasi (di luar draf & ditolak).
     *
     * @param  array<int, int>|null  $unitIds
     */
    protected function totalDigunakan(?array $unitIds): float
    {
        return (float) $this->realisasiQuery($unitIds)
            ->whereNotIn('status', [
                EnumStatusRealisasi::Draft->value,
                EnumStatusRealisasi::Ditolak->value,
                EnumStatusRealisasi::Dibatalkan->value,
            ])
            ->sum('anggaran_digunakan');
    }

    /**
     * Jumlah realisasi yang sudah diajukan (di luar draf).
     *
     * @param  array<int, int>|null  $unitIds
     */
    protected function totalRealisasiDiajukan(?array $unitIds): int
    {
        return $this->realisasiQuery($unitIds)
            ->where('status', '!=', EnumStatusRealisasi::Draft->value)
            ->count();
    }

    /**
     * Jumlah realisasi yang sudah selesai, pembanding kemajuan realisasi yang diajukan.
     *
     * @param  array<int, int>|null  $unitIds
     */
    protected function totalRealisasiSelesai(?array $unitIds): int
    {
        return $this->realisasiQuery($unitIds)
            ->where('status', EnumStatusRealisasi::Selesai->value)
            ->count();
    }

    /**
     * Kueri dasar realisasi yang sudah dibatasi tahun kerja aktif dan unit terpilih.
     *
     * @param  array<int, int>|null  $unitIds
     */
    protected function realisasiQuery(?array $unitIds): Builder
    {
        $query = KonteksProgramKerja::applyPelaksanaanVia(
            RealisasiProgramKerja::query(),
            'pengajuanProgramKerja.penawaranProgramKerja',
        );

        if ($unitIds !== null) {
            $query->whereHas('pengajuanProgramKerja', fn (Builder $q): Builder => $q->whereIn('unit_kerja_id', $unitIds));
        }

        return $query;
    }

    protected function rupiah(float $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }
}
