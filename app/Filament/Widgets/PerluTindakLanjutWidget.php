<?php

namespace App\Filament\Widgets;

use App\Enums\EnumStatusPemasukan;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Filament\Resources\Pemasukans\PemasukanResource;
use App\Filament\Resources\PengajuanProgramKerjas\PengajuanProgramKerjaResource;
use App\Filament\Resources\PerencanaanPengajuanProgramKerjas\PerencanaanPengajuanProgramKerjaResource;
use App\Filament\Resources\RealisasiProgramKerjas\RealisasiProgramKerjaResource;
use App\Filament\Widgets\Concerns\HasWidgetAuthorization;
use App\Filament\Widgets\Concerns\MerakitBarisTugas;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/**
 * Berkas milik unit kerja yang masih menunggu tindakan pengaju: dikembalikan untuk
 * diperbaiki, atau menunggu berkas penutup yang belum diunggah.
 *
 * Hanya menu yang boleh dibuka pengguna yang dihitung, dan baris tanpa pekerjaan tidak
 * ditampilkan agar yang tersisa benar-benar perlu ditindaklanjuti.
 */
class PerluTindakLanjutWidget extends Widget
{
    use HasWidgetAuthorization;
    use MerakitBarisTugas;

    protected string $view = 'filament.widgets.perlu-tindak-lanjut';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    /**
     * Berkas yang menunggu tindakan pengaju.
     *
     * @return array<int, array<string, mixed>>
     */
    public function barisTugas(): array
    {
        return $this->bersihkan([
            $this->baris(
                resource: PengajuanProgramKerjaResource::class,
                label: 'Pengajuan Perlu Revisi',
                keterangan: 'Pengajuan program kerja dikembalikan verifikator untuk diperbaiki.',
                jumlah: fn (): int => $this->hitung(PengajuanProgramKerjaResource::getEloquentQuery(), EnumStatusPengajuan::Revisi->value),
                warna: 'danger',
                icon: Heroicon::OutlinedPencilSquare,
            ),
            $this->baris(
                resource: PerencanaanPengajuanProgramKerjaResource::class,
                label: 'Pengajuan Perencanaan Perlu Revisi',
                keterangan: 'Pengajuan tahun perencanaan dikembalikan verifikator untuk diperbaiki.',
                jumlah: fn (): int => $this->hitung(PerencanaanPengajuanProgramKerjaResource::getEloquentQuery(), EnumStatusPengajuan::Revisi->value),
                warna: 'danger',
                icon: Heroicon::OutlinedPencilSquare,
            ),
            $this->baris(
                resource: RealisasiProgramKerjaResource::class,
                label: 'Realisasi Perlu Revisi',
                keterangan: 'Realisasi dikembalikan verifikator dan menunggu diajukan ulang.',
                jumlah: fn (): int => $this->hitung(RealisasiProgramKerjaResource::getEloquentQuery(), EnumStatusRealisasi::Revisi->value),
                warna: 'danger',
                icon: Heroicon::OutlinedPencilSquare,
            ),
            $this->baris(
                resource: RealisasiProgramKerjaResource::class,
                label: 'Laporan Realisasi Belum Dikirim',
                keterangan: 'Anggaran sudah dicairkan, laporan pelaksanaannya masih ditunggu.',
                jumlah: fn (): int => $this->hitung(RealisasiProgramKerjaResource::getEloquentQuery(), EnumStatusRealisasi::MenungguLaporan->value),
                warna: 'warning',
                icon: Heroicon::OutlinedDocumentArrowUp,
            ),
            $this->baris(
                resource: PemasukanResource::class,
                label: 'Pemasukan Perlu Revisi',
                keterangan: 'Pencatatan pemasukan dikembalikan verifikator untuk diperbaiki.',
                jumlah: fn (): int => $this->hitung(PemasukanResource::getEloquentQuery(), EnumStatusPemasukan::Revisi->value),
                warna: 'danger',
                icon: Heroicon::OutlinedPencilSquare,
            ),
            $this->baris(
                resource: PemasukanResource::class,
                label: 'Bukti Tanda Terima Belum Diunggah',
                keterangan: 'Pemasukan sudah disetujui dan menunggu unggahan bukti tanda terima.',
                jumlah: fn (): int => $this->hitung(PemasukanResource::getEloquentQuery(), EnumStatusPemasukan::MenungguBukti->value),
                warna: 'warning',
                icon: Heroicon::OutlinedPaperClip,
            ),
        ]);
    }
}
