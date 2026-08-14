<?php

namespace App\Filament\Resources\VerifikasiWakilRektors;

use App\Enums\EnumStatusRealisasi;
use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Filament\Resources\Concerns\HasVerificationStageScopes;
use App\Filament\Resources\RealisasiProgramKerjas\Schemas\RealisasiProgramKerjaInfolist;
use App\Filament\Resources\VerifikasiWakilRektors\Pages\ListVerifikasiWakilRektors;
use App\Filament\Resources\VerifikasiWakilRektors\Pages\ViewVerifikasiWakilRektor;
use App\Filament\Resources\VerifikasiWakilRektors\Tables\VerifikasiWakilRektorsTable;
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

class VerifikasiWakilRektorResource extends Resource
{
    use HasResourceAuthorization;
    use HasVerificationStageScopes;

    protected static ?string $model = RealisasiProgramKerja::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Verifikasi Wakil Rektor';

    protected static ?int $navigationSort = 2;

    protected static ?string $pluralLabel = 'Verifikasi Wakil Rektor';

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
        return [EnumStatusRealisasi::VerifikasiWakil->value];
    }

    public static function stageActorColumn(): string
    {
        return 'wakil_id';
    }

    public static function nextStageActorColumn(): ?string
    {
        return 'keuangan_id';
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
            "verifikasi_{$prefix}" => 'Verifikasi Realisasi (Wakil Rektor)',
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
                parent::getEloquentQuery()->with(['pengajuanProgramKerja.unitKerja', 'pengajuanProgramKerja.penawaranProgramKerja']),
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
        return VerifikasiWakilRektorsTable::configure($table);
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
            'index' => ListVerifikasiWakilRektors::route('/'),
            'view' => ViewVerifikasiWakilRektor::route('/{record}'),
        ];
    }
}
