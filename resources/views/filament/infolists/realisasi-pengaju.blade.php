{{--
    Siapa yang ditampilkan bergantung jenis realisasinya: realisasi beranggaran
    menampilkan pengaju program kerja induknya, sedangkan capaian tanpa anggaran
    menampilkan pengguna yang mencatatnya langsung dari halaman Monitoring.
--}}
<div>
    @if ($record->adalahTanpaAnggaran())
        @if ($record->dicatatOleh)
            <livewire:pengajuan-pengaju-detail
                :pengguna="$record->dicatatOleh"
                :key="'realisasi-pencatat-'.$record->getKey()"
            />
        @else
            <p class="text-sm text-gray-400 dark:text-gray-500">Pencatat capaian tidak tercatat.</p>
        @endif
    @elseif ($record->pengajuanProgramKerja)
        <livewire:pengajuan-pengaju-detail
            :record="$record->pengajuanProgramKerja"
            :key="'realisasi-pengaju-'.$record->getKey()"
        />
    @else
        <p class="text-sm text-gray-400 dark:text-gray-500">Data pengaju tidak tersedia.</p>
    @endif
</div>
