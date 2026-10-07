<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasWidgetAuthorization;
use App\Models\Periode;
use App\Models\TahunKerja;
use App\Services\KonteksProgramKerja;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * Kartu keterangan periode yang sedang berjalan: nama periode RENSTRA beserta rentang
 * waktunya, dan tahun kerja mana yang sedang dijalankan di dalamnya.
 *
 * Isinya murni acuan — tidak ada angka anggaran di sini — supaya pengguna tahu konteks
 * waktu yang sedang dipakai seluruh menu sebelum membaca angka pada kartu di bawahnya.
 * Berdampingan dengan {@see SisaWaktuTahunKerjaWidget} pada satu baris dua kolom.
 */
class PeriodeBerjalanWidget extends Widget
{
    use HasWidgetAuthorization;

    protected string $view = 'filament.widgets.periode-berjalan';

    protected static ?int $sort = -1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    /**
     * Tahun kerja yang sedang dijalankan; null berarti belum ada yang ditetapkan.
     */
    public function tahunKerja(): ?TahunKerja
    {
        return KonteksProgramKerja::tahunBerjalan();
    }

    /**
     * Periode RENSTRA tempat tahun kerja berjalan bernaung.
     */
    public function periode(): ?Periode
    {
        return $this->tahunKerja()?->periode;
    }

    /**
     * Rentang tanggal sebuah periode, mis. "1 Januari 2026 — 31 Desember 2030".
     */
    public function rentangPeriode(): ?string
    {
        $periode = $this->periode();

        return $periode === null
            ? null
            : $this->rentang($periode->start_datetime, $periode->end_datetime);
    }

    /**
     * Rentang tanggal tahun kerja yang sedang dijalankan.
     */
    public function rentangTahunKerja(): ?string
    {
        $tahunKerja = $this->tahunKerja();

        return $tahunKerja === null
            ? null
            : $this->rentang($tahunKerja->start_datetime, $tahunKerja->end_datetime);
    }

    /**
     * Berapa lama periode berlangsung, dibulatkan ke tahun terdekat sebagai acuan.
     */
    public function lamaPeriode(): ?string
    {
        $periode = $this->periode();

        if ($periode?->start_datetime === null || $periode->end_datetime === null) {
            return null;
        }

        $hari = $periode->start_datetime->diffInDays($periode->end_datetime) + 1;
        $tahun = (int) round($hari / 365);

        return $tahun > 0
            ? $tahun.' tahun kerja'
            : number_format($hari, 0, ',', '.').' hari';
    }

    private function rentang(?Carbon $mulai, ?Carbon $selesai): ?string
    {
        if ($mulai === null || $selesai === null) {
            return null;
        }

        return $this->tanggal($mulai).' — '.$this->tanggal($selesai);
    }

    private function tanggal(Carbon $waktu): string
    {
        return $waktu->locale('id')->translatedFormat('d F Y');
    }
}
