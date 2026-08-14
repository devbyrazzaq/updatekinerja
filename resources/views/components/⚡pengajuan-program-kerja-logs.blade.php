<?php

use App\Models\PengajuanProgramKerja;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public PengajuanProgramKerja $record;

    #[On('komentar-ditambahkan')]
    public function segarkan(): void
    {
        unset($this->logs);
    }

    /**
     * Seluruh log pengajuan urut kronologis, beserta aktor tiap peristiwa.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\PengajuanProgramKerjaLog>
     */
    #[Computed]
    public function logs()
    {
        return $this->record->logs()->with('user')->latest()->get();
    }
};
?>

<div class="h-[400px] space-y-6 overflow-y-auto pr-1">
    @forelse ($this->logs as $log)
        @php($aktor = $log->user)
        @php($avatar = $aktor ? \Filament\Facades\Filament::getUserAvatarUrl($aktor) : null)

        <div class="flex gap-3">
            <div class="shrink-0">
                @if ($avatar)
                    <img
                        src="{{ $avatar }}"
                        alt="{{ $aktor?->name }}"
                        class="h-12 w-12 rounded-full object-cover ring-1 ring-gray-200 dark:ring-white/10"
                    >
                @else
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                        <x-filament::icon icon="heroicon-m-cog-6-tooth" class="h-7 w-7" />
                    </span>
                @endif
            </div>

            <div class="min-w-0 flex-1">
                <p class="text-sm leading-snug text-gray-700 dark:text-gray-200">{{ trim(preg_replace('/\s+/', ' ', strip_tags($log->description))) }}</p>

                <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-400 dark:text-gray-500">
                    <x-filament::badge :color="$log->status->getColor()">{{ $log->status->getLabel() }}</x-filament::badge>
                    <span class="font-medium text-gray-500 dark:text-gray-400">{{ $aktor?->name ?? 'Sistem' }}</span>
                    <span>&middot;</span>
                    <span>{{ $log->created_at->locale('id')->translatedFormat('d F Y, H:i') }}</span>
                </div>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-400 dark:text-gray-500">Belum ada aktivitas.</p>
    @endforelse
</div>
