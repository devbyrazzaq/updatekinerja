<?php

namespace App\Filament\Resources\VerifikasiPengajuans;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Filament\Resources\Concerns\HasVerificationStageScopes;
use App\Filament\Resources\PengajuanProgramKerjas\Schemas\PengajuanProgramKerjaInfolist;
use App\Filament\Resources\VerifikasiPengajuans\Pages\ListVerifikasiPengajuans;
use App\Filament\Resources\VerifikasiPengajuans\Pages\ViewVerifikasiPengajuan;
use App\Filament\Resources\VerifikasiPengajuans\Tables\VerifikasiPengajuansTable;
use App\Models\PengajuanProgramKerja;
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

class VerifikasiPengajuanResource extends Resource
{
    use HasResourceAuthorization;
    use HasVerificationStageScopes;

    protected static ?string $model = PengajuanProgramKerja::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Verifikasi Pengajuan';

    protected static ?string $pluralLabel = 'Verifikasi Pengajuan';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Verifikasi Pengajuan';
    }

    /**
     * Slot tahun kerja yang diverifikasi menu ini. Salinannya di grup Verifikasi
     * Pengajuan Perencanaan mengembalikan slot Perencanaan sehingga kedua menu tidak
     * pernah beririsan.
     */
    public static function slotTahunKerja(): EnumStatusTahunKerja
    {
        return EnumStatusTahunKerja::Berjalan;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::pendingStageQuery()->count();
    }

    /**
     * @return array<int, string>
     */
    public static function pendingStatuses(): array
    {
        return [EnumStatusPengajuan::Diajukan->value];
    }

    public static function stageActorColumn(): string
    {
        return 'verifikator_id';
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
            "verifikasi_{$prefix}" => 'Verifikasi Pengajuan',
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
            KonteksProgramKerja::applySlotVia(
                parent::getEloquentQuery()->with(['penawaranProgramKerja', 'unitKerja', 'user']),
                static::slotTahunKerja(),
                'penawaranProgramKerja',
            )
        );
    }

    public static function infolist(Schema $schema): Schema
    {
        return PengajuanProgramKerjaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VerifikasiPengajuansTable::configure($table, static::class);
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
            'index' => ListVerifikasiPengajuans::route('/'),
            'view' => ViewVerifikasiPengajuan::route('/{record}'),
        ];
    }
}
