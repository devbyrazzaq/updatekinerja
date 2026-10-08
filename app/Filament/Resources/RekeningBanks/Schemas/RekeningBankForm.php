<?php

namespace App\Filament\Resources\RekeningBanks\Schemas;

use App\Filament\Resources\Banks\Schemas\BankForm;
use App\Filament\Resources\RekeningBanks\RekeningBankResource;
use App\Models\Bank;
use App\Models\UnitKerja;
use App\Services\UnitKerjaAktif;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RekeningBankForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Rekening Bank')
                    ->description('Rekening tujuan pencairan anggaran. Rekening milik unit kerja akan terpilih otomatis saat penjadwalan pencairan realisasi unit tersebut.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema(static::components()),
                    ]),
            ]);
    }

    /**
     * Komponen inti form, dipakai ulang oleh modal "tambah rekening" pada select
     * rekening tujuan saat penjadwalan pencairan.
     *
     * @return array<int, Select|TextInput|RichEditor|Toggle>
     */
    public static function components(?int $unitKerjaId = null): array
    {
        // Pemanggilan dengan unit kerja eksplisit (modal "tambah rekening" saat
        // penjadwalan pencairan) sudah berkonteks unit yang sedang diproses, sehingga
        // pilihannya tidak ikut dipersempit ke cakupan pengguna yang membukanya.
        $dibatasiUnitKerja = $unitKerjaId === null && static::dibatasiUnitKerja();

        return [
            // Opsi diambil lewat options() alih-alih relationship() agar komponen ini
            // tetap dapat dipakai pada modal "tambah rekening" yang tidak punya model.
            Select::make('bank_id')
                ->label('Nama Bank')
                ->options(fn (): array => Bank::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->required()
                ->helperText('Bank belum terdaftar? Tambahkan langsung lewat tombol + di samping.')
                // Bank baru dapat didaftarkan tanpa meninggalkan form rekening, mis. saat
                // unit kerja memakai bank yang belum ada di master.
                ->createOptionForm(BankForm::components())
                ->createOptionModalHeading('Tambah Bank')
                ->createOptionUsing(fn (array $data): int => Bank::create($data)->getKey())
                ->columnSpan(1),
            Select::make('unit_kerja_id')
                ->label('Unit Kerja')
                ->options(fn (): array => static::opsiUnitKerja($dibatasiUnitKerja))
                ->searchable()
                ->default($unitKerjaId ?? ($dibatasiUnitKerja ? UnitKerjaAktif::id() : null))
                ->required($dibatasiUnitKerja)
                ->selectablePlaceholder(! $dibatasiUnitKerja)
                ->placeholder($dibatasiUnitKerja ? null : 'Semua unit (umum)')
                ->helperText($dibatasiUnitKerja
                    ? 'Rekening yang Anda daftarkan melekat pada unit kerja Anda.'
                    : 'Kosongkan bila rekening berlaku umum, mis. rekening pusat.')
                ->columnSpan(1),
            TextInput::make('nomor_rekening')
                ->label('Nomor Rekening')
                ->required()
                ->maxLength(255)
                ->columnSpan(1),
            TextInput::make('atas_nama')
                ->label('Atas Nama')
                ->placeholder('Nama pemilik rekening')
                ->required()
                ->maxLength(255)
                ->columnSpan(1),
            Toggle::make('is_utama')
                ->label('Rekening Utama Unit Kerja')
                ->helperText('Rekening yang terpilih otomatis saat penjadwalan pencairan. Satu unit kerja hanya punya satu rekening utama.')
                ->columnSpanFull(),
            RichEditor::make('description')
                ->label('Keterangan')
                ->toolbarButtons([
                    'bold', 'italic', 'underline', 'strike',
                    'bulletList', 'orderedList', 'link', 'undo', 'redo',
                ])
                ->columnSpanFull(),
            Toggle::make('is_active')
                ->label('Status Aktif')
                ->default(true)
                ->columnSpanFull(),
        ];
    }

    /**
     * Unit kerja yang boleh dipilih: seluruh unit aktif, atau hanya unit yang sedang
     * aktif bagi pengguna yang cakupan datanya dibatasi.
     *
     * @return array<int, string>
     */
    protected static function opsiUnitKerja(bool $dibatasiUnitKerja): array
    {
        $query = UnitKerja::query()->where('is_active', true);

        return ($dibatasiUnitKerja ? UnitKerjaAktif::batasiKueri($query, RekeningBankResource::getPermissionName('view_any')) : $query)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Pengguna berlingkup unit wajib mendaftarkan rekening atas unitnya sendiri;
     * rekening umum (tanpa unit kerja) hanya boleh dibuat pengguna berakses penuh.
     */
    protected static function dibatasiUnitKerja(): bool
    {
        $user = auth()->user();

        return $user !== null && ! $user->canViewAllUnitData(RekeningBankResource::getPermissionName('view_any'));
    }
}
