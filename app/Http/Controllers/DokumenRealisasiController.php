<?php

namespace App\Http\Controllers;

use App\Filament\Pages\MonitoringRealisasi;
use App\Filament\Pages\PratinjauDokumenRealisasi;
use App\Services\KodeDokumenRealisasi;
use Illuminate\Http\RedirectResponse;

/**
 * Penukar kode dokumen realisasi: satu-satunya alamat yang tertanam pada berkas
 * ekspor. Kodenya dibongkar di sini, lalu pengguna dilempar ke halaman pratinjau
 * yang membuatkan URL sementara baru untuk berkasnya
 * ({@see PratinjauDokumenRealisasi}).
 *
 * Rutenya didaftarkan sebagai rute terautentikasi panel, jadi tautan yang dibuka
 * orang yang belum masuk akan singgah dulu di halaman login dan baru sampai ke
 * dokumennya setelah berhasil masuk. Hak akses dan pembatasan unit kerjanya dijaga
 * halaman tujuan, dengan aturan yang sama seperti Monitoring Realisasi.
 */
class DokumenRealisasiController extends Controller
{
    public function __invoke(string $kode): RedirectResponse
    {
        abort_unless(MonitoringRealisasi::canAccess(), 403);

        $tujuan = KodeDokumenRealisasi::bongkar($kode);

        abort_if($tujuan === null, 404, 'Tautan dokumen tidak dikenali atau sudah tidak berlaku.');

        return redirect()->to(PratinjauDokumenRealisasi::getUrl([
            'record' => $tujuan['uuid'],
            'jenis' => $tujuan['jenis']->value,
        ]));
    }
}
