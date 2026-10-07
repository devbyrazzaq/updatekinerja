<?php

namespace App\Filament\Clusters\PengaturanSistem;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Pengaturan sistem dipecah menjadi beberapa halaman agar tiap kelompok setelan berdiri
 * sendiri: satu layar satu urusan, dan hak aksesnya bisa diberikan per halaman. Menu
 * samping cluster yang menjadi jalan berpindah antar halamannya.
 */
class PengaturanSistemCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Pengaturan Sistem';

    protected static ?string $title = 'Pengaturan Sistem';

    protected static ?string $slug = 'pengaturan-sistem';

    protected static ?string $clusterBreadcrumb = 'Pengaturan Sistem';

    /**
     * Menu halaman cluster diletakkan di sisi kanan konten, bukan di kiri, agar isian
     * pengaturan tetap berada di jalur baca utama.
     */
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Pengaturan Sistem';
    }

    /**
     * Cluster tidak punya permission sendiri: yang menentukan adalah halaman di dalamnya.
     * Tanpa ini, rute cluster tetap terbuka untuk role yang tidak punya satu pun halamannya.
     */
    public static function canAccess(): bool
    {
        return static::canAccessClusteredComponents();
    }
}
