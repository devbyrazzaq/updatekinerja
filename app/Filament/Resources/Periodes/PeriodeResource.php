<?php

namespace App\Filament\Resources\Periodes;

use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Filament\Resources\Periodes\Pages\CreatePeriode;
use App\Filament\Resources\Periodes\Pages\EditPeriode;
use App\Filament\Resources\Periodes\Pages\ListPeriodes;
use App\Filament\Resources\Periodes\Pages\ViewPeriode;
use App\Filament\Resources\Periodes\Schemas\PeriodeForm;
use App\Filament\Resources\Periodes\Schemas\PeriodeInfolist;
use App\Filament\Resources\Periodes\Tables\PeriodesTable;
use App\Models\Periode;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PeriodeResource extends Resource
{
    use HasResourceAuthorization;

    protected static ?string $model = Periode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Periode';

    protected static ?string $pluralLabel = 'Data Periode';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Master Data';
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

    public static function form(Schema $schema): Schema
    {
        return PeriodeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PeriodeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PeriodesTable::configure($table);
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
            'index' => ListPeriodes::route('/'),
            'create' => CreatePeriode::route('/create'),
            'view' => ViewPeriode::route('/{record}'),
            'edit' => EditPeriode::route('/{record}/edit'),
        ];
    }
}
