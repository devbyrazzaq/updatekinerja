<?php

namespace App\Filament\Resources\VerifikasiWakilPemasukans;

use App\Enums\EnumStatusPemasukan;
use App\Filament\Resources\Concerns\HasResourceAuthorization;
use App\Filament\Resources\Concerns\HasVerificationStageScopes;
use App\Filament\Resources\Pemasukans\Schemas\PemasukanInfolist;
use App\Filament\Resources\VerifikasiWakilPemasukans\Pages\ListVerifikasiWakilPemasukans;
use App\Filament\Resources\VerifikasiWakilPemasukans\Pages\ViewVerifikasiWakilPemasukan;
use App\Filament\Resources\VerifikasiWakilPemasukans\Tables\VerifikasiWakilPemasukansTable;
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

class VerifikasiWakilPemasukanResource extends Resource
{
    use HasResourceAuthorization;
    use HasVerificationStageScopes;

    protected static ?string $model = Pemasukan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $navigationLabel = 'Verifikasi Wakil Rektor';

    protected static ?int $navigationSort = 1;

    protected static ?string $pluralLabel = 'Verifikasi Wakil Rektor';

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
     * Tahap pertama alur pemasukan, sehingga pemasukan yang baru diajukan unit kerja
     * langsung masuk antrean ini.
     *
     * @return array<int, string>
     */
    public static function pendingStatuses(): array
    {
        return [EnumStatusPemasukan::Diajukan->value, EnumStatusPemasukan::VerifikasiWakil->value];
    }

    public static function stageActorColumn(): string
    {
        return 'wakil_id';
    }

    public static function nextStageActorColumn(): ?string
    {
        return 'keuangan_id';
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
            "verifikasi_{$prefix}" => 'Verifikasi Pemasukan (Wakil Rektor)',
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
        return VerifikasiWakilPemasukansTable::configure($table);
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
            'index' => ListVerifikasiWakilPemasukans::route('/'),
            'view' => ViewVerifikasiWakilPemasukan::route('/{record}'),
        ];
    }
}
