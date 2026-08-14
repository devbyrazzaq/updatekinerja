<?php

namespace App\Filament\Pages\Widgets;

use App\Enums\EnumStatusRealisasi;
use App\Filament\Pages\BukuAnggaran;
use App\Filament\Widgets\Concerns\ScopesUnitKerja;
use App\Models\PaguAnggaran;
use App\Models\RealisasiProgramKerja;
use App\Models\TahunKerja;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Livewire\Attributes\Reactive;

/**
 * Informasi tahun kerja yang sedang dibaca Buku Anggaran beserta realisasi yang sudah
 * dilaksanakan unit kerja terpilih: jumlah realisasi, anggaran yang sudah dipakai, dan
 * porsinya terhadap pagu unit tersebut.
 *
 * Unit kerja maupun tahun kerja mengikuti penyaring di halaman {@see BukuAnggaran}.
 */
class BukuAnggaranOverview extends StatsOverviewWidget
{
    use ScopesUnitKerja;

    /**
     * Unit kerja terpilih dari halaman induk. Null berarti semua unit yang boleh diakses.
     */
    #[Reactive]
    public ?int $unitKerjaId = null;

    /**
     * Tahun kerja terpilih dari halaman induk. Null berarti tahun kerja belum ditetapkan.
     */
    #[Reactive]
    public ?int $tahunKerjaId = null;

    protected ?string $pollingInterval = null;

    protected array|int|null $columns = 3;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $tahunKerja = $this->tahunKerja();
        $jumlahRealisasi = $this->jumlahRealisasi();
        $anggaranDigunakan = $this->totalAnggaranDigunakan();
        $pagu = $this->totalPagu();

        return [
            Stat::make('Tahun Kerja', $tahunKerja?->name ?? 'Belum ditetapkan')
                ->description($this->rentangTahunKerja($tahunKerja))
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->color($tahunKerja === null ? 'gray' : 'primary'),
            Stat::make('Realisasi Dilaksanakan', (string) $jumlahRealisasi)
                ->description($this->keteranganRealisasi($jumlahRealisasi))
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color($jumlahRealisasi > 0 ? 'success' : 'gray'),
            Stat::make('Total Realisasi Anggaran', $this->rupiah($anggaranDigunakan))
                ->description($this->bandingkanPagu($anggaranDigunakan, $pagu))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color($pagu > 0 && $anggaranDigunakan > $pagu ? 'danger' : 'info'),
        ];
    }

    protected function tahunKerja(): ?TahunKerja
    {
        return $this->tahunKerjaId !== null
            ? TahunKerja::find($this->tahunKerjaId)
            : null;
    }

    /**
     * Rentang waktu tahun kerja, mis. "1 Januari 2026 – 31 Desember 2026".
     */
    protected function rentangTahunKerja(?TahunKerja $tahunKerja): string
    {
        if ($tahunKerja === null) {
            return 'Pilih tahun kerja untuk menyusun buku';
        }

        $mulai = $tahunKerja->start_datetime?->locale('id')->translatedFormat('d F Y');
        $selesai = $tahunKerja->end_datetime?->locale('id')->translatedFormat('d F Y');

        if ($mulai === null && $selesai === null) {
            return 'Rentang waktu belum ditetapkan';
        }

        return trim(($mulai ?? '...').' – '.($selesai ?? '...'));
    }

    /**
     * Jumlah realisasi yang sudah selesai sebagai pembanding realisasi yang berjalan.
     */
    protected function keteranganRealisasi(int $jumlahRealisasi): string
    {
        if ($jumlahRealisasi === 0) {
            return 'Belum ada realisasi yang dilaksanakan';
        }

        $selesai = $this->realisasiQuery()
            ->where('status', EnumStatusRealisasi::Selesai->value)
            ->count();

        return Number::percentage($selesai / $jumlahRealisasi * 100, maxPrecision: 1, locale: 'id')." sudah selesai ({$selesai} dari {$jumlahRealisasi})";
    }

    /**
     * Porsi anggaran yang terpakai terhadap pagu unit kerja pada tahun kerja ini.
     */
    protected function bandingkanPagu(float $anggaranDigunakan, float $pagu): string
    {
        if ($pagu <= 0) {
            return 'Pagu anggaran belum ditetapkan';
        }

        return Number::percentage($anggaranDigunakan / $pagu * 100, maxPrecision: 1, locale: 'id').' dari pagu '.$this->rupiah($pagu);
    }

    /**
     * Jumlah realisasi yang benar-benar dilaksanakan: draf, ditolak, dan yang dibatalkan
     * tidak dihitung karena tidak menyerap anggaran.
     */
    protected function jumlahRealisasi(): int
    {
        return $this->realisasiQuery()->count();
    }

    protected function totalAnggaranDigunakan(): float
    {
        return (float) $this->realisasiQuery()->sum('anggaran_digunakan');
    }

    /**
     * Total pagu anggaran unit kerja yang sedang dibaca pada tahun kerja terpilih,
     * menjadi pembanding seberapa besar anggaran yang sudah terpakai realisasi.
     */
    protected function totalPagu(): float
    {
        if ($this->tahunKerjaId === null) {
            return 0.0;
        }

        return (float) PaguAnggaran::query()
            ->where('tahun_kerja_id', $this->tahunKerjaId)
            ->whereIn('unit_kerja_id', $this->scopedUnitIds())
            ->sum('amount');
    }

    /**
     * Realisasi yang sudah dilaksanakan pada tahun kerja & unit kerja terpilih.
     *
     * @return Builder<RealisasiProgramKerja>
     */
    protected function realisasiQuery(): Builder
    {
        return RealisasiProgramKerja::query()
            ->whereNotIn('status', [
                EnumStatusRealisasi::Draft->value,
                EnumStatusRealisasi::Ditolak->value,
                EnumStatusRealisasi::Dibatalkan->value,
            ])
            ->whereHas('pengajuanProgramKerja', fn (Builder $pengajuan): Builder => $pengajuan
                ->whereIn('unit_kerja_id', $this->scopedUnitIds())
                ->whereHas(
                    'penawaranProgramKerja',
                    fn (Builder $penawaran): Builder => $penawaran->where('tahun_kerja_id', $this->tahunKerjaId),
                ));
    }

    protected function rupiah(float $nominal): string
    {
        return 'Rp '.number_format($nominal, 0, ',', '.');
    }
}
