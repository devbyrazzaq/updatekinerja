<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasWidgetAuthorization;
use App\Models\TahunKerja;
use App\Services\KonteksProgramKerja;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * Kartu sisa waktu tahun kerja berjalan: berapa lama lagi tahun kerja berakhir dan
 * seberapa jauh ia sudah berjalan.
 *
 * Angkanya acuan semata — tidak ada satu pun aturan sistem yang mengikutinya. Penutupan
 * tahun kerja tetap dilakukan manual lewat Pengaturan Program Kerja, jadi kartu ini
 * hanya membantu unit kerja mengukur sisa waktu menuntaskan program kerjanya.
 * Berdampingan dengan {@see PeriodeBerjalanWidget} pada satu baris dua kolom.
 */
class SisaWaktuTahunKerjaWidget extends Widget
{
    use HasWidgetAuthorization;

    protected string $view = 'filament.widgets.sisa-waktu-tahun-kerja';

    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    public function tahunKerja(): ?TahunKerja
    {
        return KonteksProgramKerja::tahunBerjalan();
    }

    /**
     * Sisa hari sampai tahun kerja berakhir. Negatif berarti tanggal berakhirnya sudah
     * terlampaui namun tahun kerja belum ditutup.
     */
    public function sisaHari(): ?int
    {
        $akhir = $this->tahunKerja()?->end_datetime;

        return $akhir === null
            ? null
            : (int) floor(Carbon::now()->startOfDay()->diffInDays($akhir->copy()->endOfDay(), false));
    }

    /**
     * Sisa waktu dalam kalimat, mis. "3 bulan 12 hari lagi".
     */
    public function sisaWaktu(): string
    {
        $sisa = $this->sisaHari();

        return match (true) {
            $sisa === null => 'Belum ditetapkan',
            $sisa < 0 => 'Sudah terlampaui',
            $sisa === 0 => 'Berakhir hari ini',
            default => $this->uraikanHari($sisa),
        };
    }

    /**
     * Persentase waktu tahun kerja yang sudah dilalui, 0–100.
     */
    public function persentaseBerjalan(): ?float
    {
        $tahunKerja = $this->tahunKerja();
        $mulai = $tahunKerja?->start_datetime;
        $akhir = $tahunKerja?->end_datetime;

        if ($mulai === null || $akhir === null) {
            return null;
        }

        $total = $mulai->diffInSeconds($akhir);

        if ($total <= 0) {
            return 100.0;
        }

        $berjalan = $mulai->diffInSeconds(Carbon::now(), false);

        return max(0.0, min(100.0, $berjalan / $total * 100));
    }

    /**
     * Warna kartu mengikuti seberapa mendesak sisa waktunya.
     */
    public function warna(): string
    {
        $sisa = $this->sisaHari();

        return match (true) {
            $sisa === null => 'gray',
            $sisa < 0 => 'danger',
            $sisa <= 30 => 'warning',
            default => 'success',
        };
    }

    public function tanggalBerakhir(): ?string
    {
        $akhir = $this->tahunKerja()?->end_datetime;

        return $akhir?->locale('id')->translatedFormat('d F Y');
    }

    /**
     * Sisa hari diuraikan menjadi bulan dan hari agar rentang panjang tetap terbaca.
     */
    private function uraikanHari(int $hari): string
    {
        if ($hari < 31) {
            return $hari.' hari lagi';
        }

        $bulan = intdiv($hari, 30);
        $sisaHari = $hari % 30;

        return $sisaHari > 0
            ? $bulan.' bulan '.$sisaHari.' hari lagi'
            : $bulan.' bulan lagi';
    }
}
