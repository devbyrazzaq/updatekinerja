{{-- Berkas unit kerja yang menunggu tindakan pengaju.
     Lihat App\Filament\Widgets\PerluTindakLanjutWidget. --}}
<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-clipboard-document-check"
        heading="Perlu Ditindaklanjuti"
        description="Berkas unit kerja yang dikembalikan atau belum dituntaskan."
        class="h-full"
    >
        @include('filament.widgets.partials.baris-tugas', [
            'baris' => $this->barisTugas(),
            'judulKosong' => 'Tidak ada berkas yang perlu ditindaklanjuti',
            'keteranganKosong' => 'Seluruh berkas unit kerja Anda sudah dituntaskan.',
        ])
    </x-filament::section>
</x-filament-widgets::widget>
