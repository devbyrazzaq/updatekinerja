@php
    use App\Enums\EnumStatusRealisasi;
    use App\Enums\EnumTahapanRealisasi;

    /** @var \App\Models\RealisasiProgramKerja $record */
    $status = $record->status;
    $current = $record->tahapanStepper();
    $stages = EnumTahapanRealisasi::flowCases();

    $stageLabel = $current->getLabel();
    $catatan = trim(strip_tags((string) $record->catatan_verifikasi));

    $callout = match ($status) {
        EnumStatusRealisasi::Revisi => [
            'color' => 'orange',
            'icon' => 'heroicon-o-pencil-square',
            'title' => "Menunggu Revisi — {$stageLabel}",
            'body' => "Realisasi dikembalikan untuk diperbaiki pada tahap {$stageLabel}. Perbaiki data lalu ajukan kembali; verifikasi dilanjutkan dari tahap ini tanpa mengulang dari awal.",
        ],
        EnumStatusRealisasi::Ditolak => [
            'color' => 'red',
            'icon' => 'heroicon-o-x-circle',
            'title' => "Realisasi Ditolak — {$stageLabel}",
            'body' => "Realisasi ditolak pada tahap {$stageLabel} dan tidak dapat dilanjutkan maupun diajukan ulang.",
        ],
        EnumStatusRealisasi::Dibatalkan => [
            'color' => 'gray',
            'icon' => 'heroicon-o-no-symbol',
            'title' => 'Pengajuan Dibatalkan',
            'body' => "Realisasi dibatalkan oleh unit kerja pada tahap {$stageLabel} dan tidak dapat dilanjutkan maupun diajukan ulang.",
        ],
        default => null,
    };
@endphp

@if ($callout !== null)
    <div @class([
        'mb-4 flex items-start gap-3 rounded-xl border p-4',
        'border-orange-200 bg-orange-50 dark:border-orange-500/30 dark:bg-orange-500/10' => $callout['color'] === 'orange',
        'border-red-200 bg-red-50 dark:border-red-500/30 dark:bg-red-500/10' => $callout['color'] === 'red',
        'border-gray-200 bg-gray-50 dark:border-gray-500/30 dark:bg-gray-500/10' => $callout['color'] === 'gray',
    ])>
        <x-filament::icon
            :icon="$callout['icon']"
            @class([
                'h-5 w-5 shrink-0',
                'text-orange-500' => $callout['color'] === 'orange',
                'text-red-500' => $callout['color'] === 'red',
                'text-gray-500' => $callout['color'] === 'gray',
            ])
        />
        <div class="space-y-1">
            <p @class([
                'text-sm font-semibold',
                'text-orange-800 dark:text-orange-200' => $callout['color'] === 'orange',
                'text-red-800 dark:text-red-200' => $callout['color'] === 'red',
                'text-gray-800 dark:text-gray-200' => $callout['color'] === 'gray',
            ])>{{ $callout['title'] }}</p>
            <p class="text-sm text-gray-600 dark:text-gray-300">{{ $callout['body'] }}</p>
            @if ($catatan !== '')
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <span class="font-medium">Catatan:</span> {{ $catatan }}
                </p>
            @endif
        </div>
    </div>
@endif

<x-filament::section>
    <x-slot name="heading">Tahapan Realisasi</x-slot>
    <x-slot name="description">Progres realisasi program kerja, dari draf hingga selesai.</x-slot>

    <div class="overflow-x-auto pb-2">
        <ol class="flex w-full min-w-[720px]">
        @foreach ($stages as $index => $stage)
            @php
                $isDone = $stage->isBefore($current);
                $isCurrent = $stage === $current;
                $isLast = $index === count($stages) - 1;

                $isRejected = $isCurrent && $status === EnumStatusRealisasi::Ditolak;
                $isRevisi = $isCurrent && $status === EnumStatusRealisasi::Revisi;
                $isDibatalkan = $isCurrent && $status === EnumStatusRealisasi::Dibatalkan;
                $isSelesai = $isCurrent && $status === EnumStatusRealisasi::Selesai;
                $isComplete = $isDone || $isSelesai;
                $isActive = $isCurrent && ! $isRejected && ! $isRevisi && ! $isDibatalkan && ! $isSelesai;
            @endphp

            <li class="relative flex flex-1 flex-col items-center px-1 text-center">
                @unless ($isLast)
                    <span @class([
                        'absolute left-1/2 top-4 h-0.5 w-full -translate-y-1/2',
                        'bg-green-500' => $isDone,
                        'bg-gray-200 dark:bg-gray-700' => ! $isDone,
                    ])></span>
                @endunless

                <span @class([
                    'relative z-10 flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold ring-4 ring-white dark:ring-gray-900',
                    'bg-green-500 text-white' => $isComplete,
                    'bg-red-500 text-white' => $isRejected,
                    'bg-orange-500 text-white' => $isRevisi,
                    'bg-gray-400 text-white' => $isDibatalkan,
                    'bg-amber-500 text-white' => $isActive,
                    'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => ! $isComplete && ! $isCurrent,
                ])>
                    @if ($isComplete)
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.5 7.6a1 1 0 0 1-1.42.006l-3.5-3.5a1 1 0 1 1 1.414-1.414l2.79 2.79 6.796-6.886a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd" />
                        </svg>
                    @elseif ($isRejected)
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                        </svg>
                    @elseif ($isRevisi)
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.793 2.232a.75.75 0 0 1-.025 1.06L3.622 7.25h10.003a5.375 5.375 0 0 1 0 10.75H10.75a.75.75 0 0 1 0-1.5h2.875a3.875 3.875 0 0 0 0-7.75H3.622l4.146 3.957a.75.75 0 0 1-1.036 1.085l-5.5-5.25a.75.75 0 0 1 0-1.085l5.5-5.25a.75.75 0 0 1 1.06.025Z" clip-rule="evenodd" />
                        </svg>
                    @elseif ($isDibatalkan)
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM5.72 5.72a.75.75 0 0 1 1.06 0L10 8.94l3.22-3.22a.75.75 0 1 1 1.06 1.06L11.06 10l3.22 3.22a.75.75 0 1 1-1.06 1.06L10 11.06l-3.22 3.22a.75.75 0 0 1-1.06-1.06L8.94 10 5.72 6.78a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                    @else
                        {{ $index + 1 }}
                    @endif
                </span>

                <span @class([
                    'mt-2 text-xs font-medium',
                    'text-red-600 dark:text-red-400' => $isRejected,
                    'text-orange-600 dark:text-orange-400' => $isRevisi,
                    'text-gray-500 dark:text-gray-400' => $isDibatalkan,
                    'text-gray-950 dark:text-white' => $isComplete || $isActive,
                    'text-gray-500 dark:text-gray-400' => ! $isComplete && ! $isCurrent,
                ])>{{ $stage->getLabel() }}</span>

                <span class="mt-1 text-[11px] leading-tight text-gray-400 dark:text-gray-500">
                    @if ($isRejected)
                        Realisasi ditolak pada tahap ini.
                    @elseif ($isRevisi)
                        Realisasi dikembalikan untuk direvisi.
                    @elseif ($isDibatalkan)
                        Pengajuan dibatalkan pada tahap ini.
                    @else
                        {{ $record->deskripsiTahapan($stage) }}
                    @endif
                </span>
            </li>
        @endforeach
        </ol>
    </div>
</x-filament::section>
