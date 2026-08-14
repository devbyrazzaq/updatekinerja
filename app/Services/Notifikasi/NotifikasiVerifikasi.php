<?php

namespace App\Services\Notifikasi;

use App\Filament\Resources\Pemasukans\PemasukanResource;
use App\Filament\Resources\PengajuanProgramKerjas\PengajuanProgramKerjaResource;
use App\Filament\Resources\RealisasiProgramKerjas\RealisasiProgramKerjaResource;
use App\Filament\Resources\VerifikasiPengajuans\VerifikasiPengajuanResource;
use App\Filament\Resources\VerifikasiRektorPemasukans\VerifikasiRektorPemasukanResource;
use App\Filament\Resources\VerifikasiRektors\VerifikasiRektorResource;
use App\Models\Pemasukan;
use App\Models\PengajuanProgramKerja;
use App\Models\RealisasiProgramKerja;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * Mengirim notifikasi database (bawaan Filament) untuk tiga peristiwa alur verifikasi:
 * pengajuan dilakukan, revisi diberikan, dan pengajuan diajukan kembali. Berlaku untuk
 * kelompok Verifikasi Pengajuan maupun Verifikasi Realisasi. Penerima ditentukan oleh
 * {@see AturanPenerimaNotifikasi}.
 */
class NotifikasiVerifikasi
{
    public function __construct(private AturanPenerimaNotifikasi $aturan = new AturanPenerimaNotifikasi) {}

    /**
     * Pengajuan program kerja diajukan atau diajukan kembali setelah revisi. Memberi
     * tahu verifikator pengajuan bahwa ada pengajuan yang menunggu.
     */
    public function pengajuanDiajukan(PengajuanProgramKerja $record, bool $diajukanKembali): void
    {
        $program = $record->penawaranProgramKerja?->name ?? 'program kerja';
        $unit = $record->unitKerja?->name;
        $nominal = Number::currency((float) $record->alokasi_anggaran, 'IDR', 'id');

        $judul = $diajukanKembali
            ? 'Pengajuan diajukan kembali'
            : 'Pengajuan menunggu verifikasi';

        $notifikasi = Notification::make()
            ->icon('heroicon-o-paper-airplane')
            ->color($diajukanKembali ? 'warning' : 'info')
            ->title($judul)
            ->body(trim(($unit ? "{$unit} — " : '')."\"{$program}\" senilai {$nominal} menunggu verifikasi."))
            ->actions([
                Action::make('lihat')
                    ->label('Lihat')
                    ->url(VerifikasiPengajuanResource::getUrl('view', ['record' => $record]))
                    ->markAsRead(),
            ]);

        $this->kirim($this->aturan->verifikatorPengajuan(), $notifikasi);
    }

    /**
     * Verifikator meminta revisi atas pengajuan. Memberi tahu pengaju asli.
     */
    public function pengajuanRevisi(PengajuanProgramKerja $record, ?string $catatan): void
    {
        $program = $record->penawaranProgramKerja?->name ?? 'program kerja';

        $notifikasi = Notification::make()
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->title('Pengajuan perlu direvisi')
            ->body($this->badanRevisi("Pengajuan \"{$program}\" diminta untuk direvisi.", $catatan))
            ->actions([
                Action::make('lihat')
                    ->label('Perbaiki')
                    ->url(PengajuanProgramKerjaResource::getUrl('view', ['record' => $record]))
                    ->markAsRead(),
            ]);

        $this->kirim($this->aturan->pengajuPengajuan($record), $notifikasi);
    }

    /**
     * Realisasi diajukan atau diajukan kembali setelah revisi. Memberi tahu verifikator
     * tahap awal realisasi.
     */
    public function realisasiDiajukan(RealisasiProgramKerja $record, bool $diajukanKembali): void
    {
        $kegiatan = $record->name ?? 'realisasi';
        $unit = $record->pengajuanProgramKerja?->unitKerja?->name;
        $urgensi = $record->urgensi;

        $judul = $diajukanKembali
            ? 'Realisasi diajukan kembali'
            : 'Realisasi menunggu verifikasi';

        $notifikasi = Notification::make()
            ->icon($urgensi?->getIcon() ?? 'heroicon-o-paper-airplane')
            ->color($urgensi?->getColor() ?? ($diajukanKembali ? 'warning' : 'info'))
            ->title($judul)
            ->body(trim(($unit ? "{$unit} — " : '')."Realisasi \"{$kegiatan}\" menunggu verifikasi."
                .($urgensi !== null ? " Urgensi: {$urgensi->getLabel()}." : '')))
            ->actions([
                Action::make('lihat')
                    ->label('Lihat')
                    ->url(VerifikasiRektorResource::getUrl('view', ['record' => $record]))
                    ->markAsRead(),
            ]);

        $this->kirim($this->aturan->verifikatorRealisasi(), $notifikasi);
    }

    /**
     * Verifikator (tahap mana pun) meminta revisi atas realisasi. Memberi tahu pengaju
     * asli realisasi.
     */
    public function realisasiRevisi(RealisasiProgramKerja $record, ?string $catatan): void
    {
        $kegiatan = $record->name ?? 'realisasi';

        $notifikasi = Notification::make()
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->title('Realisasi perlu direvisi')
            ->body($this->badanRevisi("Realisasi \"{$kegiatan}\" diminta untuk direvisi.", $catatan))
            ->actions([
                Action::make('lihat')
                    ->label('Perbaiki')
                    ->url(RealisasiProgramKerjaResource::getUrl('view', ['record' => $record]))
                    ->markAsRead(),
            ]);

        $this->kirim($this->aturan->pengajuRealisasi($record), $notifikasi);
    }

    /**
     * Verifikator (tahap mana pun) menolak realisasi sehingga tidak dapat dilanjutkan.
     * Memberi tahu pengaju asli realisasi.
     */
    public function realisasiDitolak(RealisasiProgramKerja $record, ?string $catatan): void
    {
        $kegiatan = $record->name ?? 'realisasi';

        $notifikasi = Notification::make()
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->title('Realisasi ditolak')
            ->body($this->badanRevisi("Realisasi \"{$kegiatan}\" ditolak dan tidak dapat dilanjutkan.", $catatan))
            ->actions([
                Action::make('lihat')
                    ->label('Lihat')
                    ->url(RealisasiProgramKerjaResource::getUrl('view', ['record' => $record]))
                    ->markAsRead(),
            ]);

        $this->kirim($this->aturan->pengajuRealisasi($record), $notifikasi);
    }

    /**
     * Pemasukan unit diajukan atau diajukan kembali setelah revisi. Memberi tahu
     * verifikator tahap awal pemasukan.
     */
    public function pemasukanDiajukan(Pemasukan $record, bool $diajukanKembali): void
    {
        $notifikasi = Notification::make()
            ->icon('heroicon-o-paper-airplane')
            ->color($diajukanKembali ? 'warning' : 'info')
            ->title($diajukanKembali ? 'Pemasukan diajukan kembali' : 'Pemasukan menunggu verifikasi')
            ->body($this->ringkasanPemasukan($record).' menunggu verifikasi.')
            ->actions([
                Action::make('lihat')
                    ->label('Lihat')
                    ->url(VerifikasiRektorPemasukanResource::getUrl('view', ['record' => $record]))
                    ->markAsRead(),
            ]);

        $this->kirim($this->aturan->verifikatorPemasukan(), $notifikasi);
    }

    /**
     * Verifikator (tahap mana pun) meminta revisi atas pemasukan. Memberi tahu
     * pencatat pemasukan tersebut.
     */
    public function pemasukanRevisi(Pemasukan $record, ?string $catatan): void
    {
        $notifikasi = Notification::make()
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->title('Pemasukan perlu direvisi')
            ->body($this->badanRevisi($this->ringkasanPemasukan($record).' diminta untuk direvisi.', $catatan))
            ->actions([
                Action::make('lihat')
                    ->label('Perbaiki')
                    ->url(PemasukanResource::getUrl('view', ['record' => $record]))
                    ->markAsRead(),
            ]);

        $this->kirim($this->aturan->pengajuPemasukan($record), $notifikasi);
    }

    /**
     * Verifikator (tahap mana pun) menolak pemasukan secara final. Memberi tahu
     * pencatat pemasukan tersebut.
     */
    public function pemasukanDitolak(Pemasukan $record, ?string $catatan): void
    {
        $notifikasi = Notification::make()
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->title('Pemasukan ditolak')
            ->body($this->badanRevisi($this->ringkasanPemasukan($record).' ditolak dan tidak dapat dilanjutkan.', $catatan))
            ->actions([
                Action::make('lihat')
                    ->label('Lihat')
                    ->url(PemasukanResource::getUrl('view', ['record' => $record]))
                    ->markAsRead(),
            ]);

        $this->kirim($this->aturan->pengajuPemasukan($record), $notifikasi);
    }

    /**
     * Biro Keuangan menyetujui pemasukan, sehingga unit kerja tinggal mengunggah bukti
     * tanda terima untuk mengesahkannya.
     */
    public function pemasukanMenungguBukti(Pemasukan $record): void
    {
        $notifikasi = Notification::make()
            ->icon('heroicon-o-arrow-up-tray')
            ->color('warning')
            ->title('Bukti tanda terima diperlukan')
            ->body($this->ringkasanPemasukan($record).' telah disetujui Biro Keuangan. Unggah bukti tanda terima untuk mengesahkannya.')
            ->actions([
                Action::make('lihat')
                    ->label('Unggah Bukti')
                    ->url(PemasukanResource::getUrl('view', ['record' => $record]))
                    ->markAsRead(),
            ]);

        $this->kirim($this->aturan->pengajuPemasukan($record), $notifikasi);
    }

    /**
     * Ringkasan satu pemasukan untuk badan notifikasi: unit kerja, rincian kegiatan,
     * dan nominalnya.
     */
    private function ringkasanPemasukan(Pemasukan $record): string
    {
        $kegiatan = $record->rincian_kegiatan ?? 'pemasukan';
        $unit = $record->unitKerja?->name;
        $nominal = Number::currency((float) $record->nominal_pendapatan, 'IDR', 'id');

        return trim(($unit ? "{$unit} — " : '')."Pemasukan \"{$kegiatan}\" senilai {$nominal}");
    }

    /**
     * Menambahkan cuplikan catatan revisi (tanpa HTML) ke badan notifikasi bila ada.
     */
    private function badanRevisi(string $dasar, ?string $catatan): string
    {
        $bersih = trim(strip_tags((string) $catatan));

        if ($bersih === '') {
            return $dasar;
        }

        return $dasar.' Catatan: '.Str::limit($bersih, 160);
    }

    /**
     * Mengirim notifikasi ke tabel notifications untuk seluruh penerima, dilewati bila
     * tidak ada penerima.
     *
     * @param  Collection<int, User>  $penerima
     */
    private function kirim(Collection $penerima, Notification $notifikasi): void
    {
        if ($penerima->isEmpty()) {
            return;
        }

        $notifikasi->sendToDatabase($penerima);
    }
}
