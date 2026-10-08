@php
    $pengguna = auth()->user();
    $penggunaAsli = \App\Services\Impersonasi::penggunaAsli();
@endphp

{{-- Tingginya dikunci 3rem di layar lebar: sidebar menempel tepat di bawahnya (lihat theme.css). --}}
<div class="fi-impersonasi-banner sticky top-16 z-20 border-b border-primary-600/30 bg-primary-400 text-gray-950 dark:border-primary-300/30 dark:bg-primary-500">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-2 lg:h-12 lg:flex-nowrap lg:px-6 lg:py-0">
        <div class="flex min-w-0 flex-1 items-center gap-3">
            <x-filament::icon
                :icon="\Filament\Support\Icons\Heroicon::OutlinedEye"
                class="h-5 w-5 shrink-0"
            />

            <p class="min-w-0 text-sm lg:truncate">
                Anda sedang masuk sebagai
                <span class="font-semibold">{{ $pengguna?->getFullName() }}</span>
                <span class="opacity-75">({{ $pengguna?->username }})</span>
                @if ($penggunaAsli !== null)
                    <span class="hidden opacity-75 sm:inline">&middot; akun asli {{ $penggunaAsli->getFullName() }}</span>
                @endif
            </p>
        </div>

        <form method="POST" action="{{ filament()->getDefaultPanel()->route('impersonasi.kembali') }}" class="shrink-0">
            @csrf

            <button
                type="submit"
                class="inline-flex items-center gap-1.5 rounded-full bg-gray-950 px-3.5 py-1.5 text-sm font-semibold text-white transition hover:bg-gray-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-950 active:scale-95"
            >
                <x-filament::icon
                    :icon="\Filament\Support\Icons\Heroicon::OutlinedArrowUturnLeft"
                    class="h-4 w-4"
                />
                Kembali ke akun saya
            </button>
        </form>
    </div>
</div>
