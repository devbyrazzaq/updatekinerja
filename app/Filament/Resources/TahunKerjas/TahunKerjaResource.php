<?php

namespace App\Filament\Resources\TahunKerjas;

use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Filament\Resources\TahunKerjas\Pages\CreateTahunKerja;
use App\Filament\Resources\TahunKerjas\Pages\EditTahunKerja;
use App\Filament\Resources\TahunKerjas\Pages\ListTahunKerjas;
use App\Filament\Resources\TahunKerjas\Pages\ViewTahunKerja;
use App\Filament\Resources\TahunKerjas\Schemas\TahunKerjaForm;
use App\Filament\Resources\TahunKerjas\Schemas\TahunKerjaInfolist;
use App\Filament\Resources\TahunKerjas\Tables\TahunKerjasTable;
use App\Models\TahunKerja;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TahunKerjaResource extends Resource
{
    use HasResourceAuthorization;

    protected static ?string $model = TahunKerja::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static ?string $navigationLabel = 'Tahun Kerja';

    protected static ?string $pluralLabel = 'Data Tahun Kerja';

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
        return TahunKerjaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TahunKerjaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TahunKerjasTable::configure($table);
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
            'index' => ListTahunKerjas::route('/'),
            'create' => CreateTahunKerja::route('/create'),
            'view' => ViewTahunKerja::route('/{record}'),
            'edit' => EditTahunKerja::route('/{record}/edit'),
        ];
    }
}
