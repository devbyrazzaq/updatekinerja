@php
    $mutasi = \App\Services\BukuAnggaran::untukRecord($record);
    $rupiah = fn (float $nominal): string => 'Rp '.number_format($nominal, 0, ',', '.');
@endphp

<div class="space-y-4">
    @forelse ($mutasi as $baris)
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <x-filament::badge :color="$baris->jenis->getColor()" :icon="$baris->jenis->getIcon()">
                    {{ $baris->jenis->getLabel() }}
                </x-filament::badge>

                <p class="mt-1.5 text-sm leading-snug text-gray-700 dark:text-gray-200">
                    {{ $baris->keterangan }}
                </p>

                <span class="text-xs text-gray-400 dark:text-gray-500">
                    {{ $baris->tanggal->locale('id')->translatedFormat('d F Y') }}
                </span>
            </div>

            <div class="shrink-0 text-end">
                @if ($baris->debit() > 0)
                    <span class="text-sm font-semibold text-danger-600 dark:text-danger-400">
                        &minus; {{ $rupiah($baris->debit()) }}
                    </span>
                @elseif ($baris->kredit() > 0)
                    <span class="text-sm font-semibold text-success-600 dark:text-success-400">
                        + {{ $rupiah($baris->kredit()) }}
                    </span>
                @else
                    <span class="text-sm font-semibold text-info-600 dark:text-info-400">
                        {{ $rupiah($baris->pemasukan()) }}
                    </span>
                @endif

                <span class="mt-0.5 block text-xs text-gray-400 dark:text-gray-500">
                    {{ $baris->jenis->adalahPemasukan() ? 'Tidak mengubah saldo' : 'Saldo '.$rupiah($baris->saldo) }}
                </span>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-400 dark:text-gray-500">
            Belum ada mutasi anggaran. Mutasi tercatat saat anggaran dicairkan atau selisih anggarannya dituntaskan.
        </p>
    @endforelse
</div>
