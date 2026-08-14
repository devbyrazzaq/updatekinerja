<?php

namespace App\Filament\Resources\JadwalPencairans;

use App\Enums\EnumStatusPencairan;
use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Filament\Resources\JadwalPencairans\Pages\CreateJadwalPencairan;
use App\Filament\Resources\JadwalPencairans\Pages\EditJadwalPencairan;
use App\Filament\Resources\JadwalPencairans\Pages\ListJadwalPencairans;
use App\Filament\Resources\JadwalPencairans\Pages\ViewJadwalPencairan;
use App\Filament\Resources\JadwalPencairans\RelationManagers\RealisasiProgramKerjasRelationManager;
use App\Filament\Resources\JadwalPencairans\Schemas\JadwalPencairanForm;
use App\Filament\Resources\JadwalPencairans\Schemas\JadwalPencairanInfolist;
use App\Filament\Resources\JadwalPencairans\Tables\JadwalPencairansTable;
use App\Models\JadwalPencairan;
use App\Services\KonteksProgramKerja;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class JadwalPencairanResource extends Resource
{
    use HasResourceAuthorization;

    protected static ?string $model = JadwalPencairan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    protected static ?string $navigationLabel = 'Jadwal Pencairan';

    protected static ?string $pluralLabel = 'Jadwal Pencairan';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Verifikasi Realisasi';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) self::getEloquentQuery()
            ->where('status', '!=', EnumStatusPencairan::Dicairkan->value)
            ->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    /**
     * @return array<string, string>
     */
    public static function getExtraPermissionDefinitions(): array
    {
        $prefix = static::getPermissionPrefix();

        return [
            "cairkan_{$prefix}" => 'Tandai Anggaran Dicairkan',
        ];
    }

    public static function currentUserCanCairkan(): bool
    {
        return static::currentUserCan(static::getPermissionName('cairkan'));
    }

    public static function getEloquentQuery(): Builder
    {
        return KonteksProgramKerja::applyPelaksanaan(
            parent::getEloquentQuery()->with(['realisasiProgramKerjas.pengajuanProgramKerja.unitKerja', 'realisasiProgramKerjas.rekeningBank.bank', 'keuangan']),
        );
    }

    public static function form(Schema $schema): Schema
    {
        return JadwalPencairanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return JadwalPencairanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JadwalPencairansTable::configure($table);
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [
            RealisasiProgramKerjasRelationManager::class,
        ];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListJadwalPencairans::route('/'),
            'create' => CreateJadwalPencairan::route('/create'),
            'view' => ViewJadwalPencairan::route('/{record}'),
            'edit' => EditJadwalPencairan::route('/{record}/edit'),
        ];
    }
}
