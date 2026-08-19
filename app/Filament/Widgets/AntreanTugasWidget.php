<?php

namespace App\Filament\Widgets;

use App\Enums\EnumStatusPemasukan;
use App\Enums\EnumStatusPencairan;
use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusRealisasi;
use App\Filament\Resources\JadwalPencairans\JadwalPencairanResource;
use App\Filament\Resources\Pemasukans\PemasukanResource;
use App\Filament\Resources\PengajuanProgramKerjas\PengajuanProgramKerjaResource;
use App\Filament\Resources\PerencanaanPengajuanProgramKerjas\PerencanaanPengajuanProgramKerjaResource;
use App\Filament\Resources\PerencanaanVerifikasiPengajuans\PerencanaanVerifikasiPengajuanResource;
use App\Filament\Resources\RealisasiProgramKerjas\RealisasiProgramKerjaResource;
use App\Filament\Resources\VerifikasiBiroKeuangans\VerifikasiBiroKeuanganResource;
use App\Filament\Resources\VerifikasiKeuanganPemasukans\VerifikasiKeuanganPemasukanResource;
use App\Filament\Resources\VerifikasiLaporans\VerifikasiLaporanResource;
use App\Filament\Resources\VerifikasiPengajuans\VerifikasiPengajuanResource;
use App\Filament\Resources\VerifikasiRektors\VerifikasiRektorResource;
use App\Filament\Resources\VerifikasiWakilPemasukans\VerifikasiWakilPemasukanResource;
use App\Filament\Resources\VerifikasiWakilRektors\VerifikasiWakilRektorResource;
use App\Filament\Widgets\Concerns\HasWidgetAuthorization;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daftar pekerjaan yang menunggu pengguna, dirakit dari menu yang boleh ia buka:
 * antrean verifikasi yang masih menunggu keputusannya, dan berkas miliknya sendiri
 * yang dikembalikan atau belum dituntaskan.
 *
 * Angkanya dibaca dari kueri resource masing-masing, jadi pembatasan tahun kerja
 * maupun unit kerja persis sama dengan yang akan pengguna lihat saat menu itu dibuka.
 * Baris tanpa pekerjaan tidak ditampilkan agar yang tersisa benar-benar perlu
 * ditindaklanjuti.
 */
class AntreanTugasWidget extends Widget
{
    use HasWidgetAuthorization;

    protected string $view = 'filament.widgets.antrean-tugas';

    protected static ?int $sort = 2;

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
        VerifikasiWakilPemasukanResource::class => 'Pemasukan unit kerja menunggu verifikasi Wakil Rektor.',
        VerifikasiKeuanganPemasukanResource::class => 'Pemasukan unit kerja menunggu verifikasi Biro Keuangan.',
    ];

    /**
     * Kelompok tugas yang punya isi, siap dirender.
     *
     * @return array<int, array{judul: string, keterangan: string, baris: array<int, array<string, mixed>>}>
     */
    public function kelompokTugas(): array
    {
        $kelompok = [
            [
                'judul' => 'Menunggu Keputusan Anda',
                'keterangan' => 'Berkas yang berhenti di meja Anda dan belum diverifikasi.',
                'baris' => $this->barisVerifikasi(),
            ],
            [
                'judul' => 'Perlu Ditindaklanjuti',
                'keterangan' => 'Berkas unit kerja yang dikembalikan atau belum dituntaskan.',
                'baris' => $this->barisTindakLanjut(),
            ],
        ];

        return array_values(array_filter($kelompok, fn (array $item): bool => $item['baris'] !== []));
    }

    /**
     * Antrean verifikasi beserta pencairan yang sudah dijadwalkan namun belum keluar.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function barisVerifikasi(): array
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

    /**
     * Berkas milik unit kerja yang masih menunggu tindakan pengaju: dikembalikan
     * untuk diperbaiki, atau menunggu berkas penutup yang belum diunggah.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function barisTindakLanjut(): array
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

    /**
     * Satu baris tugas. Jumlahnya baru dihitung setelah menu dipastikan boleh dibuka,
     * agar dashboard tidak menjalankan kueri untuk menu yang tidak akan tampil.
     *
     * @param  class-string  $resource
     * @param  callable(): int  $jumlah
     * @return array<string, mixed>|null
     */
    protected function baris(string $resource, string $label, string $keterangan, callable $jumlah, string $warna, string|BackedEnum|null $icon = null): ?array
    {
        if (! $resource::canAccess()) {
            return null;
        }

        return [
            'label' => $label,
            'keterangan' => $keterangan,
            'jumlah' => $jumlah(),
            'url' => $resource::getUrl(),
            'icon' => $icon ?? $resource::getNavigationIcon() ?? Heroicon::OutlinedInbox,
            'warna' => $warna,
        ];
    }

    /**
     * Membuang baris yang tidak boleh diakses maupun yang sudah bersih.
     *
     * @param  array<int, array<string, mixed>|null>  $baris
     * @return array<int, array<string, mixed>>
     */
    protected function bersihkan(array $baris): array
    {
        return array_values(array_filter(
            $baris,
            fn (?array $item): bool => $item !== null && $item['jumlah'] > 0,
        ));
    }

    protected function hitung(Builder $query, string $status): int
    {
        return $query->where('status', $status)->count();
    }
}
