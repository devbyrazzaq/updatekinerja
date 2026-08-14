<?php

namespace App\Filament\Resources\PaguAnggarans;

use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Filament\Resources\PaguAnggarans\Pages\CreatePaguAnggaran;
use App\Filament\Resources\PaguAnggarans\Pages\EditPaguAnggaran;
use App\Filament\Resources\PaguAnggarans\Pages\ListPaguAnggarans;
use App\Filament\Resources\PaguAnggarans\Pages\ViewPaguAnggaran;
use App\Filament\Resources\PaguAnggarans\Schemas\PaguAnggaranForm;
use App\Filament\Resources\PaguAnggarans\Schemas\PaguAnggaranInfolist;
use App\Filament\Resources\PaguAnggarans\Tables\PaguAnggaransTable;
use App\Models\PaguAnggaran;
use App\Models\TahunKerja;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PaguAnggaranResource extends Resource
{
    use HasResourceAuthorization;

    protected static ?string $model = PaguAnggaran::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $navigationLabel = 'Pagu Anggaran';

    protected static ?string $pluralLabel = 'Data Pagu Anggaran';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Anggaran';
    }

    public static function getNavigationBadge(): ?string
    {
        $tahunKerja = TahunKerja::berjalan();

        if (! $tahunKerja instanceof TahunKerja) {
            return null;
        }

        return (string) $tahunKerja->paguAnggarans()->count();
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
        return PaguAnggaranForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PaguAnggaranInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaguAnggaransTable::configure($table);
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
            'index' => ListPaguAnggarans::route('/'),
            'create' => CreatePaguAnggaran::route('/create'),
            'view' => ViewPaguAnggaran::route('/{record}'),
            'edit' => EditPaguAnggaran::route('/{record}/edit'),
        ];
    }
}
