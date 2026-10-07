{{-- Kartu keterangan sistem: logo, nama aplikasi, dan nama instansi dari pengaturan
     Identitas Aplikasi. Lihat App\Filament\Widgets\TentangSistemWidget.

     Tata letaknya rata kiri dan sebaris — logo di kiri, tulisan di kanannya — supaya
     sejajar dengan kartu akun di sebelahnya, yang juga rata kiri. Logonya dikunci pada
     tinggi barisnya dan lebarnya mengikuti rasio aslinya, jadi logo memanjang tidak
     dipaksa masuk kotak persegi. --}}
<x-filament-widgets::widget>
    <x-filament::section class="h-full">
        <div class="flex items-center gap-3">
            <span class="flex h-12 shrink-0 items-center overflow-hidden rounded-xl">
                <img
                    src="{{ $this->logo() }}"
                    alt="Logo {{ $this->nama() }}"
                    class="h-12 w-auto max-w-32 object-contain object-left"
                >
            </span>

            <div class="min-w-0">
                <p class="truncate text-base font-semibold tracking-[-0.02em] text-gray-950 dark:text-white">
                    {{ $this->nama() }}
                </p>
                <p class="truncate text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->instansi() }}
                </p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
