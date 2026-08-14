<?php

namespace App\Filament\Resources\PenawaranProgramKerjas;

use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Filament\Resources\PenawaranProgramKerjas\Pages\EditPenawaranProgramKerja;
use App\Filament\Resources\PenawaranProgramKerjas\Pages\ListPenawaranProgramKerjas;
use App\Filament\Resources\PenawaranProgramKerjas\Pages\ViewPenawaranProgramKerja;
use App\Filament\Resources\PenawaranProgramKerjas\Schemas\PenawaranProgramKerjaForm;
use App\Filament\Resources\PenawaranProgramKerjas\Schemas\PenawaranProgramKerjaInfolist;
use App\Filament\Resources\PenawaranProgramKerjas\Tables\PenawaranProgramKerjasTable;
use App\Models\PenawaranProgramKerja;
use App\Services\KonteksProgramKerja;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PenawaranProgramKerjaResource extends Resource
{
    use HasResourceAuthorization;

    protected static ?string $model = PenawaranProgramKerja::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?string $navigationLabel = 'Penawaran Program Kerja';

    protected static ?int $navigationSort = 3;

    protected static ?string $pluralLabel = 'Data Penawaran Program Kerja';

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
            "sinkron_{$prefix}" => 'Sinkron Ulang dari Acuan',
        ];
    }

    /**
     * Penawaran hanya lahir dari generate/sinkron di halaman Pengaturan Program Kerja,
     * bukan dari input manual maupun impor, agar selalu selaras dengan acuannya.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function currentUserCanSinkron(): bool
    {
        return static::currentUserCan(static::getPermissionName('sinkron'));
    }

    public static function getEloquentQuery(): Builder
    {
        return KonteksProgramKerja::applyPerencanaan(
            parent::getEloquentQuery()->with(['tahunKerja', 'unitKerja', 'kategori', 'program', 'rekening']),
        );
    }

    public static function form(Schema $schema): Schema
    {
        return PenawaranProgramKerjaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PenawaranProgramKerjaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PenawaranProgramKerjasTable::configure($table);
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
            'index' => ListPenawaranProgramKerjas::route('/'),
            'view' => ViewPenawaranProgramKerja::route('/{record}'),
            'edit' => EditPenawaranProgramKerja::route('/{record}/edit'),
        ];
    }
}
