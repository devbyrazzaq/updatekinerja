<?php

namespace App\Filament\Resources\VerifikasiLaporans;

use App\Enums\EnumStatusRealisasi;
use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Filament\Resources\Concerns\HasVerificationStageScopes;
use App\Filament\Resources\RealisasiProgramKerjas\Schemas\RealisasiProgramKerjaInfolist;
use App\Filament\Resources\VerifikasiLaporans\Pages\ListVerifikasiLaporans;
use App\Filament\Resources\VerifikasiLaporans\Pages\ViewVerifikasiLaporan;
use App\Filament\Resources\VerifikasiLaporans\Tables\VerifikasiLaporansTable;
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

class VerifikasiLaporanResource extends Resource
{
    use HasResourceAuthorization;
    use HasVerificationStageScopes;

    protected static ?string $model = RealisasiProgramKerja::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?string $navigationLabel = 'Verifikasi Laporan';

    protected static ?int $navigationSort = 4;

    protected static ?string $pluralLabel = 'Verifikasi Laporan';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Verifikasi Realisasi';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) self::pendingStageQuery()->count();
    }

    /**
     * @return array<int, string>
     */
    public static function pendingStatuses(): array
    {
        return [EnumStatusRealisasi::VerifikasiLaporan->value];
    }

    public static function stageActorColumn(): string
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
            "verifikasi_{$prefix}" => 'Verifikasi Laporan',
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
                parent::getEloquentQuery()->with(['pengajuanProgramKerja.unitKerja']),
                'pengajuanProgramKerja.penawaranProgramKerja',
            )
        );
    }

    public static function infolist(Schema $schema): Schema
    {
        return RealisasiProgramKerjaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VerifikasiLaporansTable::configure($table);
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListVerifikasiLaporans::route('/'),
            'view' => ViewVerifikasiLaporan::route('/{record}'),
        ];
    }
}
