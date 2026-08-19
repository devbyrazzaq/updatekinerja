<?php

namespace App\Filament\Resources\Pemasukans;

use App\Enums\EnumStatusPemasukan;
use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Filament\Resources\Pemasukans\Pages\CreatePemasukan;
use App\Filament\Resources\Pemasukans\Pages\EditPemasukan;
use App\Filament\Resources\Pemasukans\Pages\ListPemasukans;
use App\Filament\Resources\Pemasukans\Pages\ViewPemasukan;
use App\Filament\Resources\Pemasukans\Schemas\PemasukanForm;
use App\Filament\Resources\Pemasukans\Schemas\PemasukanInfolist;
use App\Filament\Resources\Pemasukans\Tables\PemasukansTable;
use App\Models\Pemasukan;
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

class PemasukanResource extends Resource
{
    use HasResourceAuthorization;

    protected static ?string $model = Pemasukan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Pemasukan Unit';

    protected static ?string $pluralLabel = 'Data Pemasukan Unit';

    protected static ?string $recordTitleAttribute = 'rincian_kegiatan';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Pemasukan';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) self::getEloquentQuery()->count();
    }

    /**
     * Badge menyala saat ada pemasukan yang menunggu tindakan unit kerja sendiri —
     * dikembalikan untuk direvisi, atau menunggu unggahan bukti tanda terima.
     */
    public static function getNavigationBadgeColor(): string|array|null
    {
        return self::getEloquentQuery()
            ->whereIn('status', [EnumStatusPemasukan::Revisi->value, EnumStatusPemasukan::MenungguBukti->value])
            ->exists()
            ? 'warning'
            : null;
    }

    public static function canEdit(Model $record): bool
    {
        return $record->dapatDiubah() && static::currentUserCan(static::getPermissionName('update'));
    }

    public static function canDelete(Model $record): bool
    {
        return $record->dapatDiubah() && static::currentUserCan(static::getPermissionName('delete'));
    }

    /**
     * @return array<string, string>
     */
    public static function getExtraPermissionDefinitions(): array
    {
        $prefix = static::getPermissionPrefix();

        return [
            "export_{$prefix}" => 'Ekspor Data',
            "report_{$prefix}" => 'Unduh Laporan PDF',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['unitKerja', 'pengajuanProgramKerja.penawaranProgramKerja', 'realisasiProgramKerja', 'pengaju', 'wakil', 'keuangan']);

        $user = auth()->user();

        if ($user !== null && ! $user->isPrivileged()) {
            $query->whereIn('unit_kerja_id', PermissionRegistrar::permittedUnitIds($user)->all());
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return PemasukanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PemasukanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PemasukansTable::configure($table);
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
            'index' => ListPemasukans::route('/'),
            'create' => CreatePemasukan::route('/create'),
            'view' => ViewPemasukan::route('/{record}'),
            'edit' => EditPemasukan::route('/{record}/edit'),
        ];
    }
}
