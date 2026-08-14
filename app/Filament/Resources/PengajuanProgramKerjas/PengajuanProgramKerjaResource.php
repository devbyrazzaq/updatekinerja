<?php

namespace App\Filament\Resources\PengajuanProgramKerjas;

use App\Enums\EnumStatusPengajuan;
use App\Enums\EnumStatusTahunKerja;
use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Filament\Resources\PengajuanProgramKerjas\Pages\CreatePengajuanProgramKerja;
use App\Filament\Resources\PengajuanProgramKerjas\Pages\EditPengajuanProgramKerja;
use App\Filament\Resources\PengajuanProgramKerjas\Pages\ListPengajuanProgramKerjas;
use App\Filament\Resources\PengajuanProgramKerjas\Pages\ViewPengajuanProgramKerja;
use App\Filament\Resources\PengajuanProgramKerjas\Schemas\PengajuanProgramKerjaForm;
use App\Filament\Resources\PengajuanProgramKerjas\Schemas\PengajuanProgramKerjaInfolist;
use App\Filament\Resources\PengajuanProgramKerjas\Tables\PengajuanProgramKerjasTable;
use App\Models\PengajuanProgramKerja;
use App\Services\KonteksProgramKerja;
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

class PengajuanProgramKerjaResource extends Resource
{
    use HasResourceAuthorization;

    protected static ?string $model = PengajuanProgramKerja::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Pengajuan Program Kerja';

    protected static ?string $pluralLabel = 'Data Pengajuan Program Kerja';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Pelaksanaan';
    }

    /**
     * Slot tahun kerja yang digarap menu ini. Salinannya di grup Perencanaan
     * mengembalikan slot Perencanaan sehingga kedua menu tidak pernah beririsan.
     */
    public static function slotTahunKerja(): EnumStatusTahunKerja
    {
        return EnumStatusTahunKerja::Berjalan;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
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
        $query = KonteksProgramKerja::applySlotVia(
            parent::getEloquentQuery()->with(['penawaranProgramKerja', 'unitKerja', 'user']),
            static::slotTahunKerja(),
            'penawaranProgramKerja',
        );

        $user = auth()->user();

        if ($user !== null && ! $user->isPrivileged()) {
            $query->whereIn('unit_kerja_id', PermissionRegistrar::permittedUnitIds($user)->all());
        }

        return $query;
    }

    /**
     * Pengajuan hanya bisa dibuat selama slot tahun kerja menu ini terisi dan tahun
     * tersebut masih membuka fase perencanaan.
     */
    public static function canCreate(): bool
    {
        return static::currentUserCan(static::getPermissionName('create'))
            && (KonteksProgramKerja::tahunSlot(static::slotTahunKerja())?->status?->bolehPerencanaan() ?? false);
    }

    public static function canEdit(Model $record): bool
    {
        return in_array($record->status, [EnumStatusPengajuan::Draft, EnumStatusPengajuan::Revisi], true)
            && static::menerimaPerencanaan($record)
            && static::currentUserCan(static::getPermissionName('update'));
    }

    public static function canDelete(Model $record): bool
    {
        return static::menerimaPerencanaan($record)
            && static::currentUserCan(static::getPermissionName('delete'));
    }

    /**
     * Tahun kerja pengajuan ini masih membuka fase perencanaan. Pengajuan tahun yang
     * sudah memasuki penutupan atau terkunci tidak boleh diubah lagi.
     */
    protected static function menerimaPerencanaan(Model $record): bool
    {
        return $record->penawaranProgramKerja?->tahunKerja?->status?->bolehPerencanaan() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return PengajuanProgramKerjaForm::configure($schema, static::slotTahunKerja());
    }

    public static function infolist(Schema $schema): Schema
    {
        return PengajuanProgramKerjaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PengajuanProgramKerjasTable::configure($table, static::slotTahunKerja());
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
            'index' => ListPengajuanProgramKerjas::route('/'),
            'create' => CreatePengajuanProgramKerja::route('/create'),
            'view' => ViewPengajuanProgramKerja::route('/{record}'),
            'edit' => EditPengajuanProgramKerja::route('/{record}/edit'),
        ];
    }
}
