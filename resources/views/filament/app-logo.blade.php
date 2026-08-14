@php
    $logo = \App\Models\Setting::brandLogoUrl();
    $nama = \App\Models\Setting::brandNama();
    $cakupan = \App\Services\UnitKerjaAktif::namaCakupan();
@endphp

<div class="flex items-center gap-3">
    @if ($logo !== null)
        <img src="{{ $logo }}" alt="Logo {{ $nama }}" class="h-11 w-auto shrink-0 object-contain">
    @endif

    <div class="min-w-0 leading-tight">
        <p class="truncate text-base font-semibold tracking-[-0.02em] text-gray-950 dark:text-white">{{ $nama }}</p>
        <p class="text-[0.7rem] font-light text-gray-500 dark:text-gray-400">{{ $cakupan }}</p>
    </div>
</div>
