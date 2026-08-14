<?php

namespace App\Filament\Resources\AcuanProgramKerjas;

use App\Filament\Resources\AcuanProgramKerjas\Pages\CreateAcuanProgramKerja;
use App\Filament\Resources\AcuanProgramKerjas\Pages\EditAcuanProgramKerja;
use App\Filament\Resources\AcuanProgramKerjas\Pages\ListAcuanProgramKerjas;
use App\Filament\Resources\AcuanProgramKerjas\Pages\ViewAcuanProgramKerja;
use App\Filament\Resources\AcuanProgramKerjas\Schemas\AcuanProgramKerjaForm;
use App\Filament\Resources\AcuanProgramKerjas\Schemas\AcuanProgramKerjaInfolist;
use App\Filament\Resources\AcuanProgramKerjas\Tables\AcuanProgramKerjasTable;
use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Models\AcuanProgramKerja;
use App\Services\KonteksProgramKerja;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AcuanProgramKerjaResource extends Resource
{
    use HasResourceAuthorization;

    protected static ?string $model = AcuanProgramKerja::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Acuan Program Kerja';

    protected static ?int $navigationSort = 2;

    protected static ?string $pluralLabel = 'Data Acuan Program Kerja';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Program Kerja';
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
        return KonteksProgramKerja::applyKelompokAcuan(
            parent::getEloquentQuery()->with(['kelompokAcuan', 'unitKerja', 'bidang', 'kategori', 'program', 'rekening', 'targets']),
        );
    }

    public static function form(Schema $schema): Schema
    {
        return AcuanProgramKerjaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AcuanProgramKerjaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AcuanProgramKerjasTable::configure($table);
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
            'index' => ListAcuanProgramKerjas::route('/'),
            'create' => CreateAcuanProgramKerja::route('/create'),
            'view' => ViewAcuanProgramKerja::route('/{record}'),
            'edit' => EditAcuanProgramKerja::route('/{record}/edit'),
        ];
    }
}
