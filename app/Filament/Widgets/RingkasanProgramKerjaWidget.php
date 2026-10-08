<?php

namespace App\Filament\Widgets;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Filament\Resources\DaftarProgramKerjas\DaftarProgramKerjaResource;
use App\Filament\Resources\PengajuanProgramKerjas\PengajuanProgramKerjaResource;
use App\Filament\Resources\RealisasiProgramKerjas\RealisasiProgramKerjaResource;
use App\Filament\Widgets\Concerns\HasWidgetAuthorization;
use App\Filament\Widgets\Concerns\MembacaTahunBerjalan;
use App\Models\PenawaranProgramKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\RealisasiProgramKerja;
use App\Services\KonteksProgramKerja;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Jumlah berkas program kerja tahun berjalan: berapa program kerja yang ditawarkan,
 * berapa yang sudah diajukan, berapa realisasinya, dan berapa yang tuntas.
 *
 * Berbeda dengan {@see RingkasanAnggaranWidget} yang berbicara rupiah, kartu ini
 * menghitung berkas — sehingga terlihat berapa banyak program kerja yang belum
 * bergerak sama sekali. Cakupannya sama: tahun kerja berjalan dan seluruh unit kerja
 * yang boleh diakses pengguna.
 */
class RingkasanProgramKerjaWidget extends StatsOverviewWidget
{
    use HasWidgetAuthorization;
    use MembacaTahunBerjalan;

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected array|int|null $columns = 4;

    protected function getHeading(): ?string
    {
        return 'Ringkasan Program Kerja '.($this->tahunKerja()?->name ?? 'Tahun Kerja Berjalan');
    }

    protected function getDescription(): ?string
    {
        return $this->tahunKerja() === null
            ? 'Belum ada tahun kerja yang dijalankan, sehingga belum ada program kerja yang dihitung.'
            : 'Jumlah berkas program kerja pada unit kerja yang dapat Anda akses, dari penawaran sampai realisasi yang tuntas.';
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        return [
            $this->statProgramKerja(),
            $this->statPengajuan(),
            $this->statRealisasi(),
            $this->statRealisasiSelesai(),
        ];
    }

    protected function statProgramKerja(): Stat
    {
        $jumlah = $this->jumlahProgramKerja();

        return Stat::make('Total Program Kerja', $this->angka($jumlah))
            ->description($jumlah > 0
                ? 'Program kerja aktif yang ditawarkan ke unit kerja'
                : 'Belum ada program kerja yang ditawarkan')
            ->descriptionIcon(Heroicon::OutlinedRectangleStack)
            ->color($jumlah > 0 ? 'primary' : 'gray')
            ->url($this->url(DaftarProgramKerjaResource::class));
    }

    protected function statPengajuan(): Stat
    {
        $jumlah = $this->jumlahPengajuan();
        $diterima = $this->jumlahPengajuan(EnumStatusPengajuan::Diterima);

        return Stat::make('Total Pengajuan', $this->angka($jumlah))
            ->description($jumlah > 0
                ? $this->angka($diterima).' pengajuan sudah diterima'
                : 'Belum ada program kerja yang diajukan')
            ->descriptionIcon(Heroicon::OutlinedInboxArrowDown)
            ->color($jumlah > 0 ? 'info' : 'gray')
            ->url($this->url(PengajuanProgramKerjaResource::class));
    }

    protected function statRealisasi(): Stat
    {
        $jumlah = $this->jumlahRealisasi();
        $berjalan = $this->jumlahRealisasiBerjalan();

        return Stat::make('Total Realisasi', $this->angka($jumlah))
            ->description($jumlah > 0
                ? $this->angka($berjalan).' realisasi masih berjalan'
                : 'Belum ada realisasi yang dibuat')
            ->descriptionIcon(Heroicon::OutlinedRocketLaunch)
            ->color($jumlah > 0 ? 'warning' : 'gray')
            ->url($this->url(RealisasiProgramKerjaResource::class));
    }

    protected function statRealisasiSelesai(): Stat
    {
        $selesai = $this->jumlahRealisasi(EnumStatusRealisasi::Selesai);
        $total = $this->jumlahRealisasi();

        return Stat::make('Realisasi Selesai', $this->angka($selesai))
            ->description($total > 0
                ? $this->persen($selesai / $total * 100).' dari seluruh realisasi'
                : 'Belum ada realisasi yang tuntas')
            ->descriptionIcon(Heroicon::OutlinedCheckBadge)
            ->color($selesai > 0 ? 'success' : 'gray')
            ->url($this->url(RealisasiProgramKerjaResource::class));
    }

    /**
     * Program kerja aktif yang ditawarkan ke unit kerja pada tahun kerja berjalan.
     */
    protected function jumlahProgramKerja(): int
    {
        if ($this->tahunKerja() === null) {
            return 0;
        }

        return KonteksProgramKerja::applyPelaksanaan(PenawaranProgramKerja::query())
            ->where('is_active', true)
            ->whereIn('unit_kerja_id', $this->scopedUnitIds())
            ->count();
    }

    protected function jumlahPengajuan(?EnumStatusPengajuan $status = null): int
    {
        if ($this->tahunKerja() === null) {
            return 0;
        }

        return KonteksProgramKerja::applyPelaksanaanVia(
            PengajuanProgramKerja::query(),
            'penawaranProgramKerja',
        )
            ->whereIn('unit_kerja_id', $this->scopedUnitIds())
            ->when($status !== null, fn (Builder $query): Builder => $query->where('status', $status->value))
            ->count();
    }

    protected function jumlahRealisasi(?EnumStatusRealisasi $status = null): int
    {
        return $this->realisasiQuery()
            ->when($status !== null, fn (Builder $query): Builder => $query->where('status', $status->value))
            ->count();
    }

    /**
     * Realisasi yang sudah diajukan namun belum selesai maupun ditolak.
     */
    protected function jumlahRealisasiBerjalan(): int
    {
        return $this->realisasiQuery()
            ->whereIn('status', array_map(
                fn (EnumStatusRealisasi $status): string => $status->value,
                EnumStatusRealisasi::berjalan(),
            ))
            ->count();
    }

    /**
     * Realisasi tahun kerja berjalan milik unit kerja yang boleh diakses pengguna.
     *
     * @return Builder<RealisasiProgramKerja>
     */
    protected function realisasiQuery(): Builder
    {
        if ($this->tahunKerja() === null) {
            return RealisasiProgramKerja::query()->whereRaw('1 = 0');
        }

        return KonteksProgramKerja::applyPelaksanaanVia(
            RealisasiProgramKerja::query(),
            'pengajuanProgramKerja.penawaranProgramKerja',
        )->whereHas(
            'pengajuanProgramKerja',
            fn (Builder $query): Builder => $query->whereIn('unit_kerja_id', $this->scopedUnitIds()),
        );
    }

    /**
     * Tautan ke menu terkait, hanya bagi pengguna yang boleh membukanya.
     *
     * @param  class-string  $resource
     */
    protected function url(string $resource): ?string
    {
        return $resource::canAccess() ? $resource::getUrl('index') : null;
    }

    protected function angka(int $jumlah): string
    {
        return number_format($jumlah, 0, ',', '.');
    }
}
