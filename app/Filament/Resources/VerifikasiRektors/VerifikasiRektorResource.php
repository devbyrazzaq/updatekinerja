<?php

namespace App\Filament\Resources\VerifikasiRektors;

use App\Enums\EnumStatusRealisasi;
use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Filament\Resources\Concerns\HasVerificationStageScopes;
use App\Filament\Resources\RealisasiProgramKerjas\Schemas\RealisasiProgramKerjaInfolist;
use App\Filament\Resources\VerifikasiRektors\Pages\ListVerifikasiRektors;
use App\Filament\Resources\VerifikasiRektors\Pages\ViewVerifikasiRektor;
use App\Filament\Resources\VerifikasiRektors\Tables\VerifikasiRektorsTable;
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

class VerifikasiRektorResource extends Resource
{
    use HasResourceAuthorization;
    use HasVerificationStageScopes;

    protected static ?string $model = RealisasiProgramKerja::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Verifikasi Rektor';

    protected static ?int $navigationSort = 1;

    protected static ?string $pluralLabel = 'Verifikasi Rektor';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Verifikasi Realisasi';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) self::pendingStageQuery()->count();
    }

    /**
     * Merah agar antrean verifikasi menonjol dibanding badge menu lain.
     */
    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    /**
     * @return array<int, string>
     */
    public static function pendingStatuses(): array
    {
        return [EnumStatusRealisasi::Diajukan->value, EnumStatusRealisasi::VerifikasiRektor->value];
    }

    public static function stageActorColumn(): string
    {
        return 'rektor_id';
    }

    public static function nextStageActorColumn(): ?string
    {
        return 'wakil_id';
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
            "verifikasi_{$prefix}" => 'Verifikasi Realisasi (Rektor)',
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
        return VerifikasiRektorsTable::configure($table);
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
            'index' => ListVerifikasiRektors::route('/'),
            'view' => ViewVerifikasiRektor::route('/{record}'),
        ];
    }
}
