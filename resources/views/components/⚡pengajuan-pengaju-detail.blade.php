<?php

use App\Models\PengajuanProgramKerja;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?PengajuanProgramKerja $record = null;

    /**
     * Pengguna yang ditampilkan bila bukan pengaju sebuah pengajuan program kerja,
     * mis. pencatat capaian tanpa anggaran pada halaman realisasi.
     */
    public ?User $pengguna = null;

    #[Computed]
    public function pengaju(): ?User
    {
        return $this->pengguna ?? $this->record?->user;
    }

    /**
     * Jabatan pengaju diambil dari role yang dimilikinya.
     */
    #[Computed]
    public function jabatan(): ?string
    {
        $roles = $this->pengaju?->getRoleNames();

        return $roles && $roles->isNotEmpty() ? $roles->join(', ') : null;
    }
};
?>

<div class="flex items-center gap-6">
    @php($pengaju = $this->pengaju)
    @php($avatar = $pengaju ? \Filament\Facades\Filament::getUserAvatarUrl($pengaju) : null)

    <div class="shrink-0">
        @if ($avatar)
            <img
                src="{{ $avatar }}"
                alt="{{ $pengaju?->name }}"
                class="h-16 w-16 rounded-full object-cover ring-1 ring-gray-200 dark:ring-white/10"
            >
        @else
            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                <x-filament::icon icon="heroicon-m-user" class="h-8 w-8" />
            </span>
        @endif
    </div>

    <div class="min-w-0 flex-1">
        <p class="truncate text-base font-semibold text-gray-950 dark:text-white">
            {{ $pengaju?->name ?? 'Tidak diketahui' }}
        </p>
        <p class="mt-0.5 truncate text-sm text-gray-500 dark:text-gray-400">
            {{ $this->jabatan ?? 'Tanpa jabatan' }}
        </p>
        @if ($pengaju?->unitKerja)
            <p class="mt-0.5 truncate text-xs text-gray-400 dark:text-gray-500">
                {{ $pengaju->unitKerja->name }}
            </p>
        @endif
    </div>
</div>
