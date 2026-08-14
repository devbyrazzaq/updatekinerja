<?php

use App\Models\RealisasiProgramKerja;
use App\Models\RealisasiProgramKerjaLog;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use RestrictsFileUploadsToSchemaComponents;

    public RealisasiProgramKerja $record;

    #[On('komentar-realisasi-ditambahkan')]
    public function segarkan(): void
    {
        unset($this->comments);
    }

    /**
     * Komentar diambil dari log realisasi yang menyimpan catatan, urut dari yang
     * paling baru. Komentar yang sudah dihapus tetap disertakan sebagai penanda.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, RealisasiProgramKerjaLog>
     */
    #[Computed]
    public function comments()
    {
        return $this->record->logs()
            ->with('user')
            ->latest()
            ->get()
            ->filter(fn (RealisasiProgramKerjaLog $log): bool => filled($log->properties['catatan'] ?? null))
            ->values();
    }

    public function editKomentarAction(): Action
    {
        return Action::make('editKomentar')
            ->label('Edit')
            ->icon('heroicon-m-pencil-square')
            ->color('gray')
            ->modalHeading('Edit Komentar')
            ->modalSubmitActionLabel('Simpan')
            ->fillForm(fn (array $arguments): array => [
                'catatan' => $this->komentarUntuk($arguments)?->catatan() ?? '',
            ])
            ->schema([
                RichEditor::make('catatan')
                    ->label('Komentar')
                    ->required(),
            ])
            ->action(function (array $arguments, array $data): void {
                $log = $this->komentarUntuk($arguments);

                if ($log === null || ! $log->komentarDapatDieditOleh(auth()->id())) {
                    return;
                }

                $log->ubahKomentar($data['catatan']);

                unset($this->comments);

                Notification::make()->title('Komentar diperbarui')->success()->send();
            });
    }

    public function hapusKomentarAction(): Action
    {
        return Action::make('hapusKomentar')
            ->label('Hapus')
            ->icon('heroicon-m-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Hapus Komentar')
            ->modalDescription('Komentar akan ditandai sebagai dihapus dan tidak dapat dikembalikan.')
            ->action(function (array $arguments): void {
                $log = $this->komentarUntuk($arguments);

                if ($log === null || ! $log->komentarDapatDiubahOleh(auth()->id())) {
                    return;
                }

                $log->tandaiKomentarDihapus();

                unset($this->comments);

                Notification::make()->title('Komentar dihapus')->success()->send();
            });
    }

    protected function komentarUntuk(array $arguments): ?RealisasiProgramKerjaLog
    {
        return $this->comments->first(
            fn (RealisasiProgramKerjaLog $log): bool => $log->getKey() === (int) ($arguments['log'] ?? 0)
        );
    }
};
?>

<div>
    <div class="flex h-[400px] flex-col gap-4 overflow-y-auto pr-1">
        @forelse ($this->comments as $comment)
        @php($aktor = $comment->user)

        @if ($comment->sudahDihapus())
            <div class="flex justify-center">
                <span class="text-xs italic text-gray-400 dark:text-gray-500">
                    Pesan {{ $aktor?->name ?? 'Sistem' }} dihapus
                </span>
            </div>
        @else
            @php($milikSaya = $comment->milikPengguna(auth()->id()))
            @php($avatar = $aktor ? \Filament\Facades\Filament::getUserAvatarUrl($aktor) : null)
            @php($diedit = filled($comment->properties['diedit_at'] ?? null))

            <div @class(['flex gap-3', 'flex-row-reverse' => ! $milikSaya])>
                <div class="shrink-0">
                    @if ($avatar)
                        <img
                            src="{{ $avatar }}"
                            alt="{{ $aktor?->name }}"
                            class="h-12 w-12 rounded-full object-cover ring-1 ring-gray-200 dark:ring-white/10"
                        >
                    @else
                        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                            <x-filament::icon icon="heroicon-m-user" class="h-7 w-7" />
                        </span>
                    @endif
                </div>

                <div @class(['flex min-w-0 flex-1 flex-col', 'items-end' => ! $milikSaya])>
                    <div class="flex w-full items-center gap-x-2 text-xs text-gray-400 dark:text-gray-500">
                        <span class="font-medium text-gray-500 dark:text-gray-400">{{ $milikSaya ? 'Me' : ($aktor?->name ?? 'Sistem') }}</span>
                        <span>&middot;</span>
                        <span>{{ $comment->created_at->locale('id')->translatedFormat('d F Y, H:i') }}</span>
                        @if ($diedit)
                            <span>&middot;</span>
                            <span class="italic">diedit</span>
                        @endif
                        @if ($milikSaya)
                            @php($dapatDiedit = $comment->komentarDapatDieditOleh(auth()->id()))
                            <div class="ms-auto ps-2">
                                <x-filament-actions::group
                                    :actions="array_values(array_filter([
                                        $dapatDiedit ? ($this->editKomentarAction)(['log' => $comment->getKey()]) : null,
                                        ($this->hapusKomentarAction)(['log' => $comment->getKey()]),
                                    ]))"
                                    icon="heroicon-m-ellipsis-horizontal"
                                    color="gray"
                                    size="xs"
                                    dropdown-placement="bottom-end"
                                />
                            </div>
                        @endif
                    </div>

                    <div @class([
                        'prose prose-sm mt-1.5 max-w-none text-sm leading-snug text-gray-700 dark:prose-invert dark:text-gray-200',
                        'text-right' => ! $milikSaya,
                    ])>
                        {!! $comment->catatan() !!}
                    </div>
                </div>
            </div>
        @endif
    @empty
        <p class="text-sm text-gray-400 dark:text-gray-500">Belum ada komentar.</p>
    @endforelse
    </div>

    <x-filament-actions::modals />
</div>
