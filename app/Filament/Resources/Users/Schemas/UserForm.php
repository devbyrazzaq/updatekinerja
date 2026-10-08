<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\EnumJenisKelamin;
use App\Enums\EnumPermission;
use App\Models\User;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class UserForm
{
    /**
     * @param  array<int, string>  $personaRoles  Role utama menu pemanggil. Role ini
     *                                            melekat lewat menu sehingga tidak ikut
     *                                            ditawarkan sebagai role tambahan.
     * @param  array<string, string>  $pilihanPersona  Bila menu memuat lebih dari satu
     *                                                 persona, opsi persona yang boleh
     *                                                 dipilih pengguna saat ini.
     */
    public static function configure(Schema $schema, array $personaRoles = [], array $pilihanPersona = []): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pribadi')
                    ->schema([
                        FileUpload::make('avatar_url')
                            ->label('Foto Profil')
                            ->image()
                            ->imageEditor()
                            ->avatar()
                            ->acceptedFileTypes(['image/jpeg', 'image/jpg'])
                            ->directory('avatar')
                            ->maxSize(3072)
                            ->columnSpanFull(),
                        TextEntry::make('guide')
                            ->label('Petunjuk foto profil')
                            ->state('Gunakan foto formal dengan wajah terlihat jelas. Format yang diterima adalah JPG atau JPEG dengan ukuran maksimal 3MB.')
                            ->columnSpanFull(),
                        FusedGroup::make([
                            TextInput::make('front_title')
                                ->label('Gelar Depan')
                                ->placeholder('Dr., Prof.')
                                ->maxLength(255),
                            TextInput::make('name')
                                ->label('Nama Lengkap')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('back_title')
                                ->label('Gelar Belakang')
                                ->placeholder('M.Si, S.Kom.')
                                ->maxLength(255),
                        ])
                            ->label('Nama Lengkap Beserta Gelar')
                            ->columns(3)
                            ->columnSpanFull(),
                        TextInput::make('username')
                            ->label('Username')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->dehydrated(fn (string $operation): bool => $operation !== 'edit')
                            ->helperText(fn (string $operation): string => $operation === 'edit'
                                ? 'Username dikunci setelah pengguna dibuat untuk menjaga stabilitas tautan akun.'
                                : 'Digunakan untuk login. Tidak dapat diubah setelah dibuat.'),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Nomor Telepon')
                            ->tel()
                            ->placeholder('Belum tersedia')
                            ->prefix('+62')
                            ->maxLength(255),
                        Select::make('gender')
                            ->label('Jenis Kelamin')
                            ->options(EnumJenisKelamin::class)
                            ->searchable()
                            ->preload(),
                        DatePicker::make('birth_date')
                            ->label('Tanggal Lahir')
                            ->helperText('Dipakai sebagai dua digit terakhir kata sandi awal pengguna.'),
                        Checkbox::make('is_active')
                            ->label('Aktifkan Pengguna')
                            ->helperText('Pengguna aktif dapat mengakses sistem.')
                            ->default(true)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Penempatan dan Hak Akses')
                    ->schema([
                        Select::make('persona_role')
                            ->label('Jenis Akun')
                            ->options($pilihanPersona)
                            ->in(array_keys($pilihanPersona))
                            ->required()
                            ->native(false)
                            ->visible($pilihanPersona !== [])
                            // Jenis akun sendiri dikunci agar tidak tanpa sengaja
                            // menurunkan hak akses akun yang sedang dipakai.
                            ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)
                            ->afterStateHydrated(function (Select $component, ?User $record) use ($personaRoles): void {
                                if ($record !== null) {
                                    $component->state($record->roles->pluck('name')->first(
                                        fn (string $role): bool => in_array($role, $personaRoles, true),
                                    ));
                                }
                            })
                            ->helperText(fn (?User $record): ?string => $record?->is(auth()->user())
                                ? 'Jenis akun sendiri tidak dapat diubah.'
                                : null)
                            ->columnSpanFull(),
                        Select::make('unit_kerja_id')
                            ->label('Unit Kerja')
                            ->relationship('unitKerja', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('roles')
                            ->label($personaRoles === [] ? 'Role' : 'Role Tambahan')
                            ->relationship(
                                'roles',
                                'name',
                                fn (Builder $query): Builder => static::opsiRoleTambahan($query, $personaRoles),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText(match (true) {
                                $personaRoles === [] => null,
                                $pilihanPersona !== [] => 'Jenis akun di atas sudah menjadi role utama dan tidak perlu dipilih di sini.',
                                default => 'Role utama "'.implode('", "', $personaRoles).'" melekat otomatis dan tidak perlu dipilih di sini.',
                            }),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Role yang dapat ditambahkan pada akun: tanpa role utama menu, dan tanpa role
     * berakses penuh bila pengguna saat ini sendiri tidak berakses penuh — agar hak
     * akses tidak dapat dinaikkan melebihi milik pemberinya.
     *
     * @param  array<int, string>  $personaRoles
     */
    protected static function opsiRoleTambahan(Builder $query, array $personaRoles): Builder
    {
        if ($personaRoles !== []) {
            $query->whereNotIn('name', $personaRoles);
        }

        $pengguna = auth()->user();

        if (! ($pengguna instanceof User && $pengguna->isPrivileged())) {
            $query->whereDoesntHave(
                'permissions',
                fn (Builder $permissions): Builder => $permissions->where('name', EnumPermission::BypassDataScope->value),
            );
        }

        return $query;
    }
}
