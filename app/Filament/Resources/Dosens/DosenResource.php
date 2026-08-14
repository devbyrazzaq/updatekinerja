<?php

namespace App\Filament\Resources\Dosens;

use App\Enums\EnumRole;
use App\Filament\Resources\Dosens\Pages\CreateDosen;
use App\Filament\Resources\Dosens\Pages\EditDosen;
use App\Filament\Resources\Dosens\Pages\ListDosens;
use App\Filament\Resources\Dosens\Pages\ViewDosen;
use App\Filament\Resources\Users\UserResource;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Support\Icons\Heroicon;

/**
 * Salinan menu Pengguna yang khusus memuat akun berrole utama Dosen. Form, tabel,
 * dan infolistnya sama persis dengan menu Pengguna — yang berbeda hanya penyaringan
 * rolenya, dan role Dosen melekat otomatis pada akun yang dibuat dari sini.
 */
class DosenResource extends UserResource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Dosen';

    protected static ?string $modelLabel = 'Dosen';

    protected static ?string $pluralLabel = 'Data Dosen';

    protected static ?int $navigationSort = 1;

    public static ?string $personaRole = EnumRole::Dosen->value;

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListDosens::route('/'),
            'create' => CreateDosen::route('/create'),
            'view' => ViewDosen::route('/{record}'),
            'edit' => EditDosen::route('/{record}/edit'),
        ];
    }
}
