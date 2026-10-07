{{-- Antrean verifikasi yang berhenti di meja pengguna.
     Lihat App\Filament\Widgets\MenungguKeputusanWidget. --}}
<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-inbox-stack"
        heading="Menunggu Keputusan Anda"
        description="Berkas yang berhenti di meja Anda dan belum diverifikasi."
        class="h-full"
    >
        @include('filament.widgets.partials.baris-tugas', [
            'baris' => $this->barisTugas(),
            'judulKosong' => 'Tidak ada berkas yang menunggu keputusan',
            'keteranganKosong' => 'Seluruh antrean verifikasi pada menu yang dapat Anda akses sudah diputuskan.',
        ])
    </x-filament::section>
</x-filament-widgets::widget>
