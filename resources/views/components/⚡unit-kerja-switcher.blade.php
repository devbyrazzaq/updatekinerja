<?php

use App\Services\UnitKerjaAktif;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $unitKerjaId = null;

    public function mount(): void
    {
        $this->unitKerjaId = UnitKerjaAktif::id();
    }

    /**
     * Unit kerja yang boleh dipilih pengguna.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function opsi(): array
    {
        return UnitKerjaAktif::opsi();
    }

    /**
     * Berpindah unit memuat ulang halaman supaya seluruh tabel, form, dan widget
     * yang sudah terlanjur terkomposisi ikut memakai cakupan unit yang baru.
     */
    public function pilih(int $unitKerjaId): void
    {
        UnitKerjaAktif::set($unitKerjaId);

        $this->unitKerjaId = UnitKerjaAktif::id();

        $this->redirect(request()->header('Referer') ?? url()->current(), navigate: false);
    }
};
?>

<div class="-mb-4">
    @if (UnitKerjaAktif::dapatBerganti())
        <x-filament::dropdown placement="bottom-start" teleport>
            <x-slot name="trigger">
                <button
                    type="button"
                    x-bind:class="$store.sidebar.isOpen ? '' : 'justify-center'"
                    class="flex w-full items-center gap-2 rounded-lg bg-gray-50 px-2.5 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-950/10 transition hover:bg-gray-100 dark:bg-white/5 dark:text-gray-200 dark:ring-white/20 dark:hover:bg-white/10"
                >
                    <x-filament::icon icon="heroicon-m-building-office-2" class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500" />
                    <span x-show="$store.sidebar.isOpen" class="min-w-0 flex-1 truncate text-start">{{ $this->opsi[$unitKerjaId] ?? 'Pilih Unit Kerja' }}</span>
                    <x-filament::icon x-show="$store.sidebar.isOpen" icon="heroicon-m-chevron-up-down" class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500" />
                </button>
            </x-slot>

            <x-filament::dropdown.header icon="heroicon-m-building-office-2">
                Unit Kerja Aktif
            </x-filament::dropdown.header>

            <x-filament::dropdown.list>
                @foreach ($this->opsi as $id => $nama)
                    <x-filament::dropdown.list.item
                        :icon="$id === $unitKerjaId ? 'heroicon-m-check-circle' : 'heroicon-o-building-office'"
                        :color="$id === $unitKerjaId ? 'primary' : 'gray'"
                        wire:click="pilih({{ $id }})"
                    >
                        {{ $nama }}
                    </x-filament::dropdown.list.item>
                @endforeach
            </x-filament::dropdown.list>
        </x-filament::dropdown>
    @endif
</div>
