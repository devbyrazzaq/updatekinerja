<div>
    @if ($record->pengajuanProgramKerja)
        <livewire:pengajuan-pengaju-detail
            :record="$record->pengajuanProgramKerja"
            :key="'realisasi-pengaju-'.$record->getKey()"
        />
    @else
        <p class="text-sm text-gray-400 dark:text-gray-500">Data pengaju tidak tersedia.</p>
    @endif
</div>
