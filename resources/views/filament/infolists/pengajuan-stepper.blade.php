@php
    use App\Enums\EnumStatusPengajuan;
    use App\Enums\EnumTahapanPengajuan;

    /** @var \App\Models\PengajuanProgramKerja $record */
    $status = $record->status;
    $current = $status->tahapan();
    $stages = EnumTahapanPengajuan::flowCases();
@endphp

<x-filament::section>
    <x-slot name="heading">Tahapan Pengajuan</x-slot>
    <x-slot name="description">Progres pengajuan program kerja Anda, dari draf hingga diterima.</x-slot>

    <div class="pb-2">
        <ol class="flex w-full">
        @foreach ($stages as $index => $stage)
            @php
                $isDone = $stage->isBefore($current);
                $isCurrent = $stage === $current;
                $isLast = $index === count($stages) - 1;

                $isRejected = $isCurrent && $status === EnumStatusPengajuan::Ditolak;
                $isRevisi = $isCurrent && $status === EnumStatusPengajuan::Revisi;
                $isAccepted = $isCurrent && $status === EnumStatusPengajuan::Diterima;
                $isComplete = $isDone || $isAccepted;
                $isActive = $isCurrent && ! $isRejected && ! $isRevisi && ! $isAccepted;
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
                    @else
                        {{ $index + 1 }}
                    @endif
                </span>

                <span @class([
                    'mt-2 text-xs font-medium',
                    'text-red-600 dark:text-red-400' => $isRejected,
                    'text-orange-600 dark:text-orange-400' => $isRevisi,
                    'text-gray-950 dark:text-white' => $isComplete || $isActive,
                    'text-gray-500 dark:text-gray-400' => ! $isComplete && ! $isCurrent,
                ])>{{ $stage->getLabel() }}</span>

                <span class="mt-1 text-[11px] leading-tight text-gray-400 dark:text-gray-500">
                    @if ($isRejected)
                        Pengajuan ditolak pada tahap ini.
                    @elseif ($isRevisi)
                        Pengajuan dikembalikan untuk direvisi.
                    @else
                        {{ $stage->description() }}
                    @endif
                </span>
            </li>
        @endforeach
        </ol>
    </div>
</x-filament::section>
