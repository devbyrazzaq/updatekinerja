<?php

namespace App\Filament\Resources\VerifikasiRektorPemasukans;

use App\Enums\EnumStatusPemasukan;
use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Filament\Resources\Concerns\HasVerificationStageScopes;
use App\Filament\Resources\Pemasukans\Schemas\PemasukanInfolist;
use App\Filament\Resources\VerifikasiRektorPemasukans\Pages\ListVerifikasiRektorPemasukans;
use App\Filament\Resources\VerifikasiRektorPemasukans\Pages\ViewVerifikasiRektorPemasukan;
use App\Filament\Resources\VerifikasiRektorPemasukans\Tables\VerifikasiRektorPemasukansTable;
use App\Models\Pemasukan;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class VerifikasiRektorPemasukanResource extends Resource
{
    use HasResourceAuthorization;
    use HasVerificationStageScopes;

    protected static ?string $model = Pemasukan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Verifikasi Rektor';

    protected static ?int $navigationSort = 1;

    protected static ?string $pluralLabel = 'Verifikasi Rektor';

    protected static ?string $recordTitleAttribute = 'rincian_kegiatan';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Verifikasi Pemasukan';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) self::pendingStageQuery()->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    /**
     * @return array<int, string>
     */
    public static function pendingStatuses(): array
    {
        return [EnumStatusPemasukan::Diajukan->value, EnumStatusPemasukan::VerifikasiRektor->value];
    }

    public static function stageActorColumn(): string
    {
        return 'rektor_id';
    }

    public static function nextStageActorColumn(): ?string
    {
        return 'wakil_id';
    }

    /**
     * @return array<string, string>
     */
    public static function getPermissionDefinitions(): array
    {
        $prefix = static::getPermissionPrefix();

        return [
            "view_any_{$prefix}" => 'Lihat Semua',
            "view_{$prefix}" => 'Lihat Detail',
            "verifikasi_{$prefix}" => 'Verifikasi Pemasukan (Rektor)',
        ];
    }

    public static function currentUserCanVerify(): bool
    {
        return static::currentUserCan(static::getPermissionName('verifikasi'));
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

    /**
     * Berbeda dengan verifikasi realisasi, konteks tahun kerja tidak diterapkan di sini:
     * pemasukan boleh tidak tertaut pengajuan maupun realisasi sama sekali, sehingga
     * menyaring lewat relasi program kerja justru membuang record yang sah. Penyempitan
     * per periode ditangani filter tanggal pelaksanaan pada tabel.
     *
     * Verifikator juga melihat seluruh unit kerja, jadi batasan unit milik
     * PemasukanResource tidak berlaku di sini.
     */
    public static function getEloquentQuery(): Builder
    {
        return static::applyStageScope(
            parent::getEloquentQuery()->with([
                'unitKerja',
                'pengajuanProgramKerja.penawaranProgramKerja',
                'realisasiProgramKerja',
                'pengaju',
                'rektor',
                'wakil',
                'keuangan',
            ])
        );
    }

    public static function infolist(Schema $schema): Schema
    {
        return PemasukanInfolist::configure($schema, static::class);
    }

    public static function table(Table $table): Table
    {
        return VerifikasiRektorPemasukansTable::configure($table);
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
            'index' => ListVerifikasiRektorPemasukans::route('/'),
            'view' => ViewVerifikasiRektorPemasukan::route('/{record}'),
        ];
    }
}
