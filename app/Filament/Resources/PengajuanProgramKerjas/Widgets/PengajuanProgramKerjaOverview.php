<?php

namespace App\Filament\Resources\PengajuanProgramKerjas\Widgets;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusTahunKerja;
use App\Models\PengajuanProgramKerja;
use App\Models\UnitKerja;
use App\Services\KonteksProgramKerja;
use App\Services\PermissionRegistrar;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;

/**
 * Ringkasan Pengajuan Program Kerja mengikuti unit kerja yang dipilih di halaman.
 * Bila belum ada unit yang dipilih, angka dihitung dari seluruh unit yang boleh
 * diakses pengguna.
 */
class PengajuanProgramKerjaOverview extends StatsOverviewWidget
{
    /**
     * Unit kerja terpilih dari halaman induk. Null berarti semua unit yang boleh diakses.
     * Reaktif agar ringkasan ikut berubah saat pilihan unit di halaman diperbarui.
     */
    #[Reactive]
    public ?int $unitKerjaId = null;

    /**
     * Permission menu induk widget ini, diisi halaman induk lewat data widget. Menjadi
     * dasar apakah akses seluruh unit pengguna berlaku di menu tersebut (lihat
     * User::canViewAllUnitData()). Terkunci agar tidak bisa diganti dari peramban.
     */
    #[Locked]
    public ?string $permissionLingkup = null;

    protected ?string $pollingInterval = null;

    protected array|int|null $columns = 3;

    /**
     * Slot tahun kerja yang diringkas. Turunannya di grup Perencanaan meringkas slot
     * Perencanaan sehingga angkanya sejalan dengan tabel di bawahnya.
     */
    protected function slotTahunKerja(): EnumStatusTahunKerja
    {
        return EnumStatusTahunKerja::Berjalan;
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $unitIds = $this->scopedUnitIds();

        return [
            $this->unitStat($unitIds),
            Stat::make('Total Anggaran Disetujui', $this->rupiah($this->totalAnggaranDisetujui($unitIds)))
                ->description($this->unitKerjaId !== null
                    ? 'Anggaran pengajuan diterima unit terpilih'
                    : 'Akumulasi anggaran pengajuan diterima')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make('Total Pengajuan', (string) $this->totalPengajuan($unitIds))
                ->description('Pengajuan program kerja tahun kerja aktif')
                ->descriptionIcon(Heroicon::OutlinedInboxArrowDown)
                ->color('info'),
        ];
    }

    /**
     * Stat unit kerja: nama unit terpilih, atau ringkasan jumlah unit bila semua ditampilkan.
     *
     * @param  array<int, int>|null  $unitIds
     */
    protected function unitStat(?array $unitIds): Stat
    {
        if ($this->unitKerjaId !== null) {
            $nama = UnitKerja::query()->whereKey($this->unitKerjaId)->value('name') ?? 'Unit tidak ditemukan';

            return Stat::make('Unit Kerja', $nama)
                ->description('Unit kerja yang sedang ditampilkan')
                ->descriptionIcon(Heroicon::OutlinedBuildingOffice2)
                ->color('gray');
        }

        $jumlahUnit = $unitIds === null
            ? UnitKerja::query()->count()
            : count($unitIds);

        return Stat::make('Unit Kerja', 'Semua Unit')
            ->description($jumlahUnit.' unit kerja tercakup')
            ->descriptionIcon(Heroicon::OutlinedBuildingOffice2)
            ->color('gray');
    }

    /**
     * Id unit kerja yang menjadi dasar perhitungan. Null berarti tanpa batasan (seluruh unit).
     *
     * @return array<int, int>|null
     */
    protected function scopedUnitIds(): ?array
    {
        if ($this->unitKerjaId !== null) {
            return [$this->unitKerjaId];
        }

        $user = auth()->user();

        if ($user === null || $user->canViewAllUnitData($this->permissionLingkup)) {
            return null;
        }

        return PermissionRegistrar::permittedUnitIds($user)->all();
    }

    /**
     * Total alokasi anggaran pengajuan yang berstatus diterima, mengikuti tahun kerja aktif.
     *
     * @param  array<int, int>|null  $unitIds
     */
    protected function totalAnggaranDisetujui(?array $unitIds): float
    {
        return (float) $this->baseQuery($unitIds)
            ->where('status', EnumStatusPengajuan::Diterima)
            ->sum('alokasi_anggaran');
    }

    /**
     * @param  array<int, int>|null  $unitIds
     */
    protected function totalPengajuan(?array $unitIds): int
    {
        return $this->baseQuery($unitIds)->count();
    }

    /**
     * Kueri dasar pengajuan yang sudah dibatasi tahun kerja aktif dan unit terpilih.
     *
     * @param  array<int, int>|null  $unitIds
     */
    protected function baseQuery(?array $unitIds): Builder
    {
        $query = KonteksProgramKerja::applySlotVia(
            PengajuanProgramKerja::query(),
            $this->slotTahunKerja(),
            'penawaranProgramKerja',
        );

        if ($unitIds !== null) {
            $query->whereIn('unit_kerja_id', $unitIds);
        }

        return $query;
    }

    protected function rupiah(float $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }
}
