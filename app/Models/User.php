<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\EnumJenisKelamin;
use App\Enums\EnumPermission;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'avatar_url',
    'name',
    'front_title',
    'back_title',
    'username',
    'email',
    'phone',
    'birth_date',
    'gender',
    'unit_kerja_id',
    'is_active',
    'password',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Akses penuh tanpa pembatasan kepemilikan/penugasan data. Berbasis
     * permission agar tidak bergantung pada nama role admin.
     */
    public function isPrivileged(): bool
    {
        return $this->can(EnumPermission::BypassDataScope->value);
    }

    public function getRouteKeyName(): string
    {
        return 'username';
    }

    /**
     * Simpan hash kata sandi apa adanya, melewati cast `hashed`. Dipakai saat
     * memindahkan akun dari aplikasi lain: cast bawaan menolak hash yang dibuat
     * dengan cost bcrypt berbeda dari konfigurasi aplikasi ini, padahal hash
     * tersebut tetap sah dan harus dipertahankan agar kata sandi lama berlaku.
     */
    public function setHashedPassword(string $hash): void
    {
        $this->attributes['password'] = $hash;
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    /**
     * Nama lengkap beserta gelar depan dan gelar belakang.
     */
    public function getFullName(): string
    {
        return trim("{$this->front_title} {$this->name} {$this->back_title}");
    }

    /**
     * Umur dalam tahun; `null` bila tanggal lahir belum diisi.
     */
    public function getAge(): ?int
    {
        return $this->birth_date !== null ? Carbon::parse($this->birth_date)->age : null;
    }

    /**
     * Foto profil disimpan pada disk privat sehingga diakses lewat URL sementara.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        if ($this->avatar_url) {
            return Storage::disk(config('filament.default_filesystem_disk'))
                ->temporaryUrl($this->avatar_url, now()->addHour());
        }

        return $this->getDefaultFilamentAvatarUrl();
    }

    /**
     * Avatar cadangan berupa inisial nama pengguna.
     */
    public function getDefaultFilamentAvatarUrl(): string
    {
        $name = str(Filament::getNameForDefaultAvatar($this))
            ->trim()
            ->explode(' ')
            ->map(fn (string $segment): string => filled($segment) ? mb_substr($segment, 0, 1) : '')
            ->join(' ');

        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&color=FFFFFF&background='.urlencode(FilamentColor::getColor('gray')[950] ?? Color::Gray[950]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'gender' => EnumJenisKelamin::class,
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
