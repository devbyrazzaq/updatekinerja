<?php

namespace App\Filament\Resources\Rekenings;

use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Filament\Resources\Rekenings\Pages\CreateRekening;
use App\Filament\Resources\Rekenings\Pages\EditRekening;
use App\Filament\Resources\Rekenings\Pages\ListRekenings;
use App\Filament\Resources\Rekenings\Pages\ViewRekening;
use App\Filament\Resources\Rekenings\Schemas\RekeningForm;
use App\Filament\Resources\Rekenings\Schemas\RekeningInfolist;
use App\Filament\Resources\Rekenings\Tables\RekeningsTable;
use App\Models\Rekening;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RekeningResource extends Resource
{
    use HasResourceAuthorization;

    protected static ?string $model = Rekening::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'C.O.A';

    protected static ?string $label = 'C.O.A';

    protected static ?string $pluralLabel = 'Data C.O.A';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Anggaran';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) self::getEloquentQuery()->count();
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
            "import_{$prefix}" => 'Impor Data',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('unitKerja');
    }

    public static function form(Schema $schema): Schema
    {
        return RekeningForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RekeningInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RekeningsTable::configure($table);
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
            'index' => ListRekenings::route('/'),
            'create' => CreateRekening::route('/create'),
            'view' => ViewRekening::route('/{record}'),
            'edit' => EditRekening::route('/{record}/edit'),
        ];
    }
}
