<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\EnumJenisKelamin;
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
     * @param  string|null  $personaRole  Role utama menu pemanggil. Bila diisi, role
     *                                    tersebut melekat otomatis pada akun sehingga
     *                                    tidak ikut ditawarkan sebagai role tambahan.
     */
    public static function configure(Schema $schema, ?string $personaRole = null): Schema
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
                        Select::make('unit_kerja_id')
                            ->label('Unit Kerja')
                            ->relationship('unitKerja', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('roles')
                            ->label($personaRole === null ? 'Role' : 'Role Tambahan')
                            ->relationship(
                                'roles',
                                'name',
                                fn (Builder $query): Builder => $personaRole === null
                                    ? $query
                                    : $query->where('name', '!=', $personaRole),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText($personaRole === null
                                ? null
                                : "Role utama \"{$personaRole}\" melekat otomatis dan tidak perlu dipilih di sini."),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
