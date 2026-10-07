{{-- Daftar baris tugas dashboard, dipakai bersama widget "Menunggu Keputusan Anda" dan
     "Perlu Ditindaklanjuti". Kosongnya pun ditangani di sini agar kedua kartu bersisian
     dengan bentuk yang sama.

     @param array<int, array<string, mixed>> $baris
     @param string $judulKosong
     @param string $keteranganKosong --}}
@if ($baris === [])
    <div class="flex flex-col items-center gap-2 py-6 text-center">
        <x-filament::icon
            icon="heroicon-o-check-circle"
            class="h-8 w-8 text-success-500"
        />

        <span class="text-sm font-medium text-gray-950 dark:text-white">
            {{ $judulKosong }}
        </span>

        <span class="text-xs text-gray-500 dark:text-gray-400">
            {{ $keteranganKosong }}
        </span>
    </div>
@else
    <div class="divide-y divide-gray-100 dark:divide-white/10">
        @foreach ($baris as $item)
            <a
                href="{{ $item['url'] }}"
                class="flex items-center gap-3 py-2.5 transition hover:bg-gray-50 dark:hover:bg-white/5"
            >
                <x-filament::icon
                    :icon="$item['icon']"
                    @class([
                        'h-5 w-5 shrink-0',
                        'text-danger-500' => $item['warna'] === 'danger',
                        'text-warning-500' => $item['warna'] === 'warning',
                        'text-info-500' => $item['warna'] === 'info',
                    ])
                />

                <div class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-gray-950 dark:text-white">
                        {{ $item['label'] }}
                    </span>

                    <span class="block truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ $item['keterangan'] }}
                    </span>
                </div>

                <x-filament::badge :color="$item['warna']">
                    {{ number_format($item['jumlah'], 0, ',', '.') }}
                </x-filament::badge>
            </a>
        @endforeach
    </div>
@endif
