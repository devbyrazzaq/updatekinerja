<?php

namespace App\Filament\Resources\RekeningBanks;

use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Filament\Resources\RekeningBanks\Pages\CreateRekeningBank;
use App\Filament\Resources\RekeningBanks\Pages\EditRekeningBank;
use App\Filament\Resources\RekeningBanks\Pages\ListRekeningBanks;
use App\Filament\Resources\RekeningBanks\Pages\ViewRekeningBank;
use App\Filament\Resources\RekeningBanks\Schemas\RekeningBankForm;
use App\Filament\Resources\RekeningBanks\Schemas\RekeningBankInfolist;
use App\Filament\Resources\RekeningBanks\Tables\RekeningBanksTable;
use App\Models\RekeningBank;
use App\Services\PermissionRegistrar;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class RekeningBankResource extends Resource
{
    use HasResourceAuthorization;

    protected static ?string $model = RekeningBank::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Rekening Bank';

    protected static ?string $pluralLabel = 'Data Rekening Bank';

    protected static ?string $recordTitleAttribute = 'nomor_rekening';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Master Data';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) self::getEloquentQuery()->count();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['bank', 'unitKerja']);

        $user = auth()->user();

        if ($user !== null && ! $user->isPrivileged()) {
            // Rekening umum (tanpa unit kerja) tetap terlihat karena juga menjadi
            // pilihan pencairan unit mana pun.
            $unitIds = PermissionRegistrar::permittedUnitIds($user)->all();

            $query->where(fn (Builder $scoped): Builder => $scoped
                ->whereIn('unit_kerja_id', $unitIds)
                ->orWhereNull('unit_kerja_id'));
        }

        return $query;
    }

    public static function canEdit(Model $record): bool
    {
        return static::dapatDikelola($record) && static::currentUserCan(static::getPermissionName('update'));
    }

    public static function canDelete(Model $record): bool
    {
        return static::dapatDikelola($record) && static::currentUserCan(static::getPermissionName('delete'));
    }

    /**
     * Pengguna berlingkup unit hanya boleh mengubah rekening milik unitnya sendiri;
     * rekening umum yang dipakai bersama tetap menjadi wewenang pengguna berakses
     * penuh walau ikut terlihat di daftar.
     */
    protected static function dapatDikelola(Model $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->isPrivileged()) {
            return true;
        }

        return $record->unit_kerja_id !== null
            && PermissionRegistrar::permittedUnitIds($user)->contains($record->unit_kerja_id);
    }

    public static function form(Schema $schema): Schema
    {
        return RekeningBankForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RekeningBankInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RekeningBanksTable::configure($table);
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
            'index' => ListRekeningBanks::route('/'),
            'create' => CreateRekeningBank::route('/create'),
            'view' => ViewRekeningBank::route('/{record}'),
            'edit' => EditRekeningBank::route('/{record}/edit'),
        ];
    }
}
