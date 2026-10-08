<?php

namespace App\Filament\Resources\Concerns;

use App\Models\UnitKerja;
use App\Services\PermissionRegistrar;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/**
 * Penyaring unit kerja di badan halaman (bukan filter tabel) beserta bawaannya.
 * Sengaja berupa properti halaman agar ringkasan yang memakai trait HasUnitKerjaStat
 * ikut berubah lewat properti reaktif, dan agar penyaringan bisa mengikuti scope data
 * pengguna: yang tidak berwenang atas seluruh data selalu memulai dari satu unit.
 */
trait HasUnitKerjaPageFilter
{
    /**
     * Unit kerja yang dipilih untuk penyaringan tampilan. Null berarti seluruh unit
     * yang boleh diakses ditampilkan.
     */
    public ?int $unitKerjaId = null;

    /**
     * Cache pilihan unit kerja agar tidak dikueri berulang dalam satu request.
     *
     * @var array<int, string>|null
     */
    protected ?array $cachedUnitKerjaOptions = null;

    /**
     * Panel penyaring, ditempatkan di paling atas badan halaman sehingga mendahului
     * ringkasan dan tabel yang angkanya ditentukan olehnya.
     */
    protected function penyaringUnitKerjaSection(): Section
    {
        $unitKerjaOptions = $this->unitKerjaOptions();
        $pilihanTunggal = count($unitKerjaOptions) <= 1;

        return Section::make('Tampilkan Data')
            ->description($pilihanTunggal
                ? 'Data yang tampil dibatasi pada unit kerja yang menjadi cakupan Anda.'
                : 'Pilih unit kerja untuk menyaring data yang tampil. Kosongkan untuk menampilkan seluruh unit.')
            ->icon(Heroicon::OutlinedFunnel)
            ->collapsible()
            ->schema([
                Select::make('unitKerjaId')
                    ->label('Unit Kerja')
                    ->placeholder('Semua Unit Kerja')
                    ->options($unitKerjaOptions)
                    ->disabled($pilihanTunggal)
                    ->searchable()
                    ->native(false)
                    ->live(),
            ]);
    }

    /**
     * Unit kerja yang boleh dipilih pengguna, mengikuti scope data yang dimiliki.
     *
     * @return array<int, string>
     */
    protected function unitKerjaOptions(): array
    {
        if ($this->cachedUnitKerjaOptions !== null) {
            return $this->cachedUnitKerjaOptions;
        }

        $query = UnitKerja::query()->where('is_active', true)->orderBy('name');

        $user = auth()->user();

        if ($user !== null && ! $user->canViewAllUnitData(static::getResource()::getPermissionName('view_any'))) {
            $query->whereIn('id', PermissionRegistrar::permittedUnitIds($user)->all());
        }

        return $this->cachedUnitKerjaOptions = $query->pluck('name', 'id')->all();
    }

    /**
     * Pengguna yang tidak berwenang atas seluruh data selalu memulai dari unit pertama
     * yang boleh diaksesnya, bukan dari gabungan semua unit.
     */
    protected function unitKerjaBawaan(): ?int
    {
        $user = auth()->user();

        if ($user === null || $user->canViewAllUnitData(static::getResource()::getPermissionName('view_any'))) {
            return null;
        }

        return array_key_first($this->unitKerjaOptions());
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            'unitKerjaId' => $this->unitKerjaId,
            'permissionLingkup' => static::getResource()::getPermissionName('view_any'),
        ];
    }
}
