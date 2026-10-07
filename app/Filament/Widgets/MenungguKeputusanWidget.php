<?php

namespace App\Filament\Widgets;

use App\Enums\EnumStatusPencairan;
use App\Filament\Resources\JadwalPencairans\JadwalPencairanResource;
use App\Filament\Resources\PerencanaanVerifikasiPengajuans\PerencanaanVerifikasiPengajuanResource;
use App\Filament\Resources\VerifikasiBiroKeuangans\VerifikasiBiroKeuanganResource;
use App\Filament\Resources\VerifikasiKeuanganPemasukans\VerifikasiKeuanganPemasukanResource;
use App\Filament\Resources\VerifikasiLaporanLampaus\VerifikasiLaporanLampauResource;
use App\Filament\Resources\VerifikasiLaporans\VerifikasiLaporanResource;
use App\Filament\Resources\VerifikasiPengajuans\VerifikasiPengajuanResource;
use App\Filament\Resources\VerifikasiRektors\VerifikasiRektorResource;
use App\Filament\Resources\VerifikasiWakilPemasukans\VerifikasiWakilPemasukanResource;
use App\Filament\Resources\VerifikasiWakilRektors\VerifikasiWakilRektorResource;
use App\Filament\Widgets\Concerns\HasWidgetAuthorization;
use App\Filament\Widgets\Concerns\MerakitBarisTugas;
use Filament\Widgets\Widget;

/**
 * Berkas yang berhenti di meja pengguna: antrean verifikasi yang menunggu keputusannya,
 * beserta anggaran yang sudah dijadwalkan namun belum ditandai dicairkan.
 *
 * Hanya antrean dari menu yang boleh ia buka yang dihitung, dan baris tanpa pekerjaan
 * tidak ditampilkan agar yang tersisa benar-benar perlu ditindaklanjuti.
 */
class MenungguKeputusanWidget extends Widget
{
    use HasWidgetAuthorization;
    use MerakitBarisTugas;

    protected string $view = 'filament.widgets.menunggu-keputusan';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    /**
     * Antrean verifikasi menurut urutan alurnya: keterangan tiap resource ditulis
     * di sini karena label navigasinya sendiri berulang antar alur (mis. "Verifikasi
     * Rektor" ada pada realisasi maupun pemasukan).
     *
     * @var array<class-string, string>
     */
    protected const ANTREAN_VERIFIKASI = [
        VerifikasiPengajuanResource::class => 'Pengajuan program kerja tahun berjalan menunggu keputusan Anda.',
        PerencanaanVerifikasiPengajuanResource::class => 'Pengajuan program kerja tahun perencanaan menunggu keputusan Anda.',
        VerifikasiRektorResource::class => 'Realisasi program kerja menunggu verifikasi Rektor.',
        VerifikasiWakilRektorResource::class => 'Realisasi program kerja menunggu verifikasi Wakil Rektor.',
        VerifikasiBiroKeuanganResource::class => 'Realisasi program kerja menunggu verifikasi Biro Keuangan.',
        VerifikasiLaporanResource::class => 'Laporan realisasi menunggu diverifikasi.',
        VerifikasiLaporanLampauResource::class => 'Laporan realisasi tahun lalu menunggu diverifikasi.',
        VerifikasiWakilPemasukanResource::class => 'Pemasukan unit kerja menunggu verifikasi Wakil Rektor.',
        VerifikasiKeuanganPemasukanResource::class => 'Pemasukan unit kerja menunggu verifikasi Biro Keuangan.',
    ];

    /**
     * Antrean verifikasi beserta pencairan yang sudah dijadwalkan namun belum keluar.
     *
     * @return array<int, array<string, mixed>>
     */
    public function barisTugas(): array
    {
        $baris = [];

        foreach (static::ANTREAN_VERIFIKASI as $resource => $keterangan) {
            $baris[] = $this->baris(
                resource: $resource,
                label: $resource::getNavigationLabel(),
                keterangan: $keterangan,
                jumlah: fn (): int => $resource::pendingStageQuery()->count(),
                warna: 'warning',
            );
        }

        $baris[] = $this->baris(
            resource: JadwalPencairanResource::class,
            label: 'Pencairan Anggaran',
            keterangan: 'Anggaran sudah dijadwalkan namun belum ditandai dicairkan.',
            jumlah: fn (): int => JadwalPencairanResource::getEloquentQuery()
                ->where('status', '!=', EnumStatusPencairan::Dicairkan->value)
                ->count(),
            warna: 'info',
        );

        return $this->bersihkan($baris);
    }
}
