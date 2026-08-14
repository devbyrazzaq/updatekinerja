<?php

namespace App\Filament\Resources\RealisasiProgramKerjas;

use App\Enums\EnumStatusRealisasi;
use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Filament\Resources\RealisasiProgramKerjas\Pages\CreateRealisasiProgramKerja;
use App\Filament\Resources\RealisasiProgramKerjas\Pages\EditRealisasiProgramKerja;
use App\Filament\Resources\RealisasiProgramKerjas\Pages\ListRealisasiProgramKerjas;
use App\Filament\Resources\RealisasiProgramKerjas\Pages\ViewRealisasiProgramKerja;
use App\Filament\Resources\RealisasiProgramKerjas\Schemas\RealisasiProgramKerjaForm;
use App\Filament\Resources\RealisasiProgramKerjas\Schemas\RealisasiProgramKerjaInfolist;
use App\Filament\Resources\RealisasiProgramKerjas\Tables\RealisasiProgramKerjasTable;
use App\Models\RealisasiProgramKerja;
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

class RealisasiProgramKerjaResource extends Resource
{
    use HasResourceAuthorization;

    protected static ?string $model = RealisasiProgramKerja::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Realisasi Program Kerja';

    protected static ?string $pluralLabel = 'Data Realisasi Program Kerja';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Pelaksanaan';
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
        ];
    }

    /**
     * Realisasi hanya lahir dari tahun kerja yang benar-benar berjalan. Tanpa tahun
     * berjalan — misalnya ketika yang ada baru tahun perencanaan — tidak ada
     * anggaran yang sudah berlaku, sehingga tombol tambah ikut ditutup.
     */
    public static function canCreate(): bool
    {
        return static::currentUserCan(static::getPermissionName('create'))
            && KonteksProgramKerja::tahunBerjalan() !== null;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = KonteksProgramKerja::applyPelaksanaanVia(
            parent::getEloquentQuery()->with(['pengajuanProgramKerja.unitKerja', 'pengajuanProgramKerja.penawaranProgramKerja']),
            'pengajuanProgramKerja.penawaranProgramKerja',
        );

        $user = auth()->user();

        if ($user !== null && ! $user->isPrivileged()) {
            $unitIds = PermissionRegistrar::permittedUnitIds($user)->all();
            $query->whereHas('pengajuanProgramKerja', fn (Builder $q) => $q->whereIn('unit_kerja_id', $unitIds));
        }

        return $query;
    }

    /**
     * Revisi pada tahap verifikasi laporan tidak lewat form edit realisasi karena yang
     * perlu diperbaiki adalah laporannya, bukan data pengajuan realisasinya; unit kerja
     * memakai aksi "Perbaiki Laporan".
     */
    public static function canEdit(Model $record): bool
    {
        return in_array($record->status, [EnumStatusRealisasi::Draft, EnumStatusRealisasi::Revisi], true)
            && ! $record->adalahRevisiLaporan()
            && static::currentUserCan(static::getPermissionName('update'));
    }

    public static function canDelete(Model $record): bool
    {
        return $record->status === EnumStatusRealisasi::Draft
            && static::currentUserCan(static::getPermissionName('delete'));
    }

    public static function form(Schema $schema): Schema
    {
        return RealisasiProgramKerjaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RealisasiProgramKerjaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RealisasiProgramKerjasTable::configure($table);
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
            'index' => ListRealisasiProgramKerjas::route('/'),
            'create' => CreateRealisasiProgramKerja::route('/create'),
            'view' => ViewRealisasiProgramKerja::route('/{record}'),
            'edit' => EditRealisasiProgramKerja::route('/{record}/edit'),
        ];
    }
}
