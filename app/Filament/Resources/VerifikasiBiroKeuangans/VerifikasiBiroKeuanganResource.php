<?php

namespace App\Filament\Resources\VerifikasiBiroKeuangans;

use App\Enums\EnumStatusRealisasi;
use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Filament\Resources\Concerns\HasVerificationStageScopes;
use App\Filament\Resources\RealisasiProgramKerjas\Schemas\RealisasiProgramKerjaInfolist;
use App\Filament\Resources\VerifikasiBiroKeuangans\Pages\ListVerifikasiBiroKeuangans;
use App\Filament\Resources\VerifikasiBiroKeuangans\Tables\VerifikasiBiroKeuangansTable;
use App\Models\RealisasiProgramKerja;
use App\Services\KonteksProgramKerja;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class VerifikasiBiroKeuanganResource extends Resource
{
    use HasResourceAuthorization;
    use HasVerificationStageScopes;

    protected static ?string $model = RealisasiProgramKerja::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Verifikasi Biro Keuangan';

    protected static ?int $navigationSort = 3;

    protected static ?string $pluralLabel = 'Verifikasi Biro Keuangan';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Verifikasi Realisasi';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) self::pendingStageQuery()->count();
    }

    /**
     * Biro Keuangan menangani dua langkah berturut-turut: menjadwalkan pencairan
     * (VerifikasiKeuangan) lalu menandai anggaran benar-benar dicairkan
     * (Dijadwalkan). Keduanya masih perlu tindakan sehingga tetap di tab utama;
     * begitu dicairkan, status menjadi MenungguLaporan dan pindah ke tab
     * "Sudah Diproses".
     *
     * @return array<int, string>
     */
    public static function pendingStatuses(): array
    {
        return [
            EnumStatusRealisasi::VerifikasiKeuangan->value,
            EnumStatusRealisasi::Dijadwalkan->value,
        ];
    }

    public static function stageActorColumn(): string
    {
        return 'keuangan_id';
    }

    public static function nextStageActorColumn(): ?string
    {
        return 'verifikator_laporan_id';
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    /**
     * @return array<string, string>
     */
    public static function getPermissionDefinitions(): array
    {
        $prefix = static::getPermissionPrefix();

        return [
            "view_any_{$prefix}" => 'Lihat Semua',
            "view_{$prefix}" => 'Lihat Detail',
            "verifikasi_{$prefix}" => 'Proses Pencairan Anggaran',
        ];
    }

    public static function currentUserCanVerify(): bool
    {
        return static::currentUserCan(static::getPermissionName('verifikasi'));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return static::applyStageScope(
            KonteksProgramKerja::applyPelaksanaanVia(
                parent::getEloquentQuery()->with(['pengajuanProgramKerja.unitKerja', 'jadwalPencairan', 'rekeningBank.bank']),
                'pengajuanProgramKerja.penawaranProgramKerja',
            )
        );
    }

    public static function infolist(Schema $schema): Schema
    {
        return RealisasiProgramKerjaInfolist::configure($schema, static::class);
    }

    public static function table(Table $table): Table
    {
        return VerifikasiBiroKeuangansTable::configure($table);
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [];
    }

    /**
     * Detail sengaja tidak punya halaman sendiri: `ViewAction` pada tabel akan
     * membukanya sebagai modal beserta aksi pencairan di footer, sehingga
     * Biro Keuangan tidak perlu berpindah halaman.
     *
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListVerifikasiBiroKeuangans::route('/'),
        ];
    }
}
