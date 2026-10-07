<?php

namespace App\Filament\Resources\VerifikasiLaporanLampaus;

use App\Filament\Pages\PenyelesaianTahunLalu;
use App\Filament\Resources\VerifikasiLaporanLampaus\Pages\ListVerifikasiLaporanLampaus;
use App\Filament\Resources\VerifikasiLaporanLampaus\Pages\ViewVerifikasiLaporanLampau;
use App\Filament\Resources\VerifikasiLaporans\Tables\VerifikasiLaporansTable;
use App\Filament\Resources\VerifikasiLaporans\VerifikasiLaporanResource;
use App\Services\KonteksProgramKerja;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Verifikasi laporan realisasi milik tahun kerja yang sudah masuk Penutupan. Menu
 * Verifikasi Laporan hanya berbicara tentang tahun berjalan, sehingga laporan
 * tunggakan yang diunggah unit kerja dari {@see PenyelesaianTahunLalu}
 * diverifikasi di sini, terpisah dan tidak tercampur angka tahun berjalan.
 *
 * Alur dan aksinya sama persis dengan Verifikasi Laporan; yang berbeda hanya tahun
 * kerja yang dicakup dan permission-nya sendiri.
 */
class VerifikasiLaporanLampauResource extends VerifikasiLaporanResource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxArrowDown;

    protected static ?string $navigationLabel = 'Verifikasi Laporan Lampau';

    protected static ?int $navigationSort = 5;

    protected static ?string $pluralLabel = 'Verifikasi Laporan Lampau';

    /**
     * Menu disembunyikan selama tidak ada tahun Penutupan: tanpa tahun yang sedang
     * ditutup, tidak ada laporan lampau yang perlu diverifikasi.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return parent::shouldRegisterNavigation() && KonteksProgramKerja::tahunPenutupanIds() !== [];
    }

    public static function getNavigationBadge(): ?string
    {
        $jumlah = self::pendingStageQuery()->count();

        return $jumlah > 0 ? (string) $jumlah : null;
    }

    public static function getEloquentQuery(): Builder
    {
        $tahunPenutupanIds = KonteksProgramKerja::tahunPenutupanIds();

        return static::applyStageScope(
            static::getModel()::query()
                ->with(['pengajuanProgramKerja.unitKerja', 'pengajuanProgramKerja.penawaranProgramKerja.tahunKerja'])
                ->whereHas(
                    'pengajuanProgramKerja.penawaranProgramKerja',
                    fn (Builder $query): Builder => $query->whereIn('tahun_kerja_id', $tahunPenutupanIds),
                )
        );
    }

    public static function table(Table $table): Table
    {
        return VerifikasiLaporansTable::configure($table, KonteksProgramKerja::tahunPenutupanIds())
            ->emptyStateHeading('Tidak ada laporan lampau yang menunggu verifikasi')
            ->emptyStateDescription('Laporan realisasi tahun kerja yang sedang ditutup, yang diunggah unit kerja dari menu Penyelesaian Tahun Lalu, akan tampil di sini.');
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListVerifikasiLaporanLampaus::route('/'),
            'view' => ViewVerifikasiLaporanLampau::route('/{record}'),
        ];
    }
}
