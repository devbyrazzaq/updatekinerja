<?php

namespace App\Filament\Resources\DaftarProgramKerjas;

use App\Enums\EnumStatusTahunKerja;
use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Filament\Resources\DaftarProgramKerjas\Pages\ListDaftarProgramKerjas;
use App\Filament\Resources\DaftarProgramKerjas\Pages\ViewDaftarProgramKerja;
use App\Filament\Resources\DaftarProgramKerjas\Schemas\DaftarProgramKerjaInfolist;
use App\Filament\Resources\DaftarProgramKerjas\Tables\DaftarProgramKerjasTable;
use App\Models\PenawaranProgramKerja;
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

class DaftarProgramKerjaResource extends Resource
{
    use HasResourceAuthorization;

    protected static ?string $model = PenawaranProgramKerja::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Daftar Program Kerja';

    protected static ?string $pluralLabel = 'Daftar Program Kerja';

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

    /**
     * Jumlah program kerja yang ditawarkan pada slot tahun kerja menu ini, sesuai
     * scope data pengguna.
     */
    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    /**
     * Hanya izinkan view (katalog + aksi Ajukan). Tidak ada create/edit/delete.
     */
    public static function getExtraPermissionDefinitions(): array
    {
        return [];
    }

    public static function getPermissionDefinitions(): array
    {
        $prefix = static::getPermissionPrefix();

        return [
            "view_any_{$prefix}" => 'Lihat Semua',
            "view_{$prefix}" => 'Lihat Detail',
            "ajukan_{$prefix}" => 'Ajukan Program Kerja',
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = KonteksProgramKerja::applySlot(
            parent::getEloquentQuery()
                ->with(['kategori', 'program', 'rekening', 'unitKerja'])
                ->where('is_active', true),
            static::slotTahunKerja(),
        );

        $user = auth()->user();

        if ($user !== null && ! $user->isPrivileged()) {
            $query->whereIn('unit_kerja_id', PermissionRegistrar::permittedUnitIds($user)->all());
        }

        return $query;
    }

    public static function infolist(Schema $schema): Schema
    {
        return DaftarProgramKerjaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DaftarProgramKerjasTable::configure($table);
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
            'index' => ListDaftarProgramKerjas::route('/'),
            'view' => ViewDaftarProgramKerja::route('/{record}'),
        ];
    }
}
