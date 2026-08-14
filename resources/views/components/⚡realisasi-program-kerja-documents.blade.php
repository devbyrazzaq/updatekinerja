<?php

use App\Filament\Actions\MediaAction;
use App\Models\RealisasiProgramKerja;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public RealisasiProgramKerja $record;

    /**
     * Nama relasi dokumen yang ditampilkan: `proposals` atau `laporans`.
     */
    public string $relationship;

    /**
     * Teks pengganti saat belum ada berkas, mis. "Belum ada proposal.".
     */
    public string $emptyLabel;

    /**
     * Menampilkan tombol pratinjau per berkas. Dimatikan bila pemanggilnya sudah
     * menyediakan tombol pratinjau sendiri (mis. di header section).
     */
    public bool $dapatPratinjau = false;

    /**
     * Berkas pada relasi terpilih, memakai urutan bawaan relasi (unggahan terbaru dulu).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\RealisasiDokumen>
     */
    #[Computed]
    public function dokumens()
    {
        return $this->record->{$this->relationship}()->get();
    }

    /**
     * Pratinjau satu berkas. Berkasnya ditentukan lewat argumen `dokumen` yang
     * dikirim saat aksi dipanggil di dalam perulangan.
     */
    public function pratinjauAction(): MediaAction
    {
        return MediaAction::make('pratinjau')
            ->label('Pratinjau')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->iconButton()
            ->path(fn (array $arguments): ?string => $this->dokumens
                ->firstWhere('id', $arguments['dokumen'] ?? null)
                ?->path);
    }
};
?>

<div class="space-y-2">
    @forelse ($this->dokumens as $dokumen)
        <div class="flex items-start gap-3 rounded-xl bg-gray-50 px-3 py-2.5 dark:bg-white/5">
            <span class="mt-0.5 shrink-0 text-gray-400 dark:text-gray-500">
                <x-filament::icon icon="heroicon-o-document-text" class="h-5 w-5" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium leading-snug text-gray-950 dark:text-white">
                    <span class="break-words">{{ $dokumen->nama_tampilan }}</span>

                    @if ($dokumen->ukuran_terbaca)
                        <span class="font-normal text-gray-500 dark:text-gray-400">({{ $dokumen->ukuran_terbaca }})</span>
                    @endif
                </p>

                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    @if ($dokumen->uploaded_at)
                        Diunggah {{ $dokumen->uploaded_at->locale('id')->translatedFormat('d F Y, H:i') }}
                    @else
                        Tanggal unggah tidak tercatat
                    @endif
                </p>
            </div>

            @if ($dapatPratinjau)
                <div class="shrink-0">
                    {{ ($this->pratinjauAction)(['dokumen' => $dokumen->getKey()]) }}
                </div>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-400 dark:text-gray-500">{{ $emptyLabel }}</p>
    @endforelse

    @if ($dapatPratinjau)
        <x-filament-actions::modals />
    @endif
</div>
