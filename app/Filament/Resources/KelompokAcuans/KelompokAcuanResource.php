<?php

namespace App\Filament\Resources\KelompokAcuans;

use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Filament\Resources\KelompokAcuans\Pages\CreateKelompokAcuan;
use App\Filament\Resources\KelompokAcuans\Pages\EditKelompokAcuan;
use App\Filament\Resources\KelompokAcuans\Pages\ListKelompokAcuans;
use App\Filament\Resources\KelompokAcuans\Pages\ViewKelompokAcuan;
use App\Filament\Resources\KelompokAcuans\RelationManagers\AcuanProgramKerjasRelationManager;
use App\Filament\Resources\KelompokAcuans\Schemas\KelompokAcuanForm;
use App\Filament\Resources\KelompokAcuans\Schemas\KelompokAcuanInfolist;
use App\Filament\Resources\KelompokAcuans\Tables\KelompokAcuansTable;
use App\Models\KelompokAcuan;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class KelompokAcuanResource extends Resource
{
    use HasResourceAuthorization;

    protected static ?string $model = KelompokAcuan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Kelompok Acuan';

    protected static ?string $pluralLabel = 'Data Kelompok Acuan Program Kerja';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Program Kerja';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) self::getEloquentQuery()->count();
    }

    public static function form(Schema $schema): Schema
    {
        return KelompokAcuanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return KelompokAcuanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KelompokAcuansTable::configure($table);
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [
            AcuanProgramKerjasRelationManager::class,
        ];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListKelompokAcuans::route('/'),
            'create' => CreateKelompokAcuan::route('/create'),
            'view' => ViewKelompokAcuan::route('/{record}'),
            'edit' => EditKelompokAcuan::route('/{record}/edit'),
        ];
    }
}
