{{-- Kartu aksi cepat dashboard, bentuknya mengikuti Pintasan Menu — bedanya kartu di
     sini tidak berpindah halaman melainkan membuka slide-over di tempat.
     Lihat App\Filament\Widgets\AksiCepatWidget.

     Pemicunya memakai handler bawaan aksi Filament (mis. mountAction('buatPengajuan')),
     dan modalnya dirender <x-filament-actions::modals /> di bawah. --}}
@php
    $aksi = $this->aksiCepat();
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-bolt"
        heading="Aksi Cepat"
        description="Mulai pekerjaan yang paling sering dilakukan tanpa berpindah menu."
    >
        @if ($aksi === [])
            <div class="flex flex-col items-center gap-2 py-6 text-center">
                <x-filament::icon
                    icon="heroicon-o-lock-closed"
                    class="h-8 w-8 text-gray-400"
                />

                <span class="text-sm font-medium text-gray-950 dark:text-white">
                    Belum ada aksi yang dapat Anda jalankan
                </span>

                <span class="text-xs text-gray-500 dark:text-gray-400">
                    Hubungi administrator bila Anda memerlukan akses membuat berkas baru.
                </span>
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($aksi as $item)
                    <button
                        type="button"
                        wire:click="{{ $item['pemicu'] }}"
                        wire:loading.attr="disabled"
                        class="flex items-start gap-3 rounded-xl p-3 text-left ring-1 ring-gray-950/5 transition hover:bg-gray-50 disabled:pointer-events-none disabled:opacity-70 dark:ring-white/10 dark:hover:bg-white/5"
                    >
                        <span @class([
                            'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
                            'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' => $item['warna'] === 'primary',
                            'bg-info-50 text-info-600 dark:bg-info-500/10 dark:text-info-400' => $item['warna'] === 'info',
                            'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400' => $item['warna'] === 'success',
                            'bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400' => $item['warna'] === 'warning',
                        ])>
                            <x-filament::icon :icon="$item['icon']" class="h-5 w-5" />
                        </span>

                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium text-gray-950 dark:text-white">
                                {{ $item['label'] }}
                            </span>

                            <span class="block text-xs text-gray-500 dark:text-gray-400">
                                {{ $item['keterangan'] }}
                            </span>
                        </span>
                    </button>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
