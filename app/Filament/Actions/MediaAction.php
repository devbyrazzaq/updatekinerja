<?php

namespace App\Filament\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;

/**
 * Pratinjau berkas (PDF/gambar) di dalam modal, tanpa meninggalkan halaman.
 * Berkas disimpan pada disk privat sehingga ditampilkan lewat URL sementara.
 * Mendukung satu maupun banyak berkas (atribut bernilai array).
 */
class MediaAction extends Action
{
    protected Closure|string|null $path = null;

    public static function getDefaultName(): ?string
    {
        return 'media';
    }

    /**
     * Berkas yang ditampilkan: nama atribut record (mis. 'proposal_path', boleh berisi
     * string tunggal atau array) atau closure yang mengembalikan path/array path.
     */
    public function path(Closure|string|null $path): static
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Daftar path berkas yang valid, hasil normalisasi nilai tunggal maupun array.
     *
     * @return array<int, string>
     */
    public function getPaths(): array
    {
        $value = is_string($this->path)
            ? $this->getRecord()?->getAttribute($this->path)
            : $this->evaluate($this->path);

        $paths = is_array($value) ? $value : [$value];

        return array_values(array_filter(
            $paths,
            fn ($path): bool => is_string($path) && filled($path),
        ));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Lihat Dokumen')
            ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
            ->color('primary')
            ->visible(fn (): bool => $this->getPaths() !== [])
            ->modalHeading(fn (): string => (string) $this->getLabel())
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalAutofocus(false)
            ->modalContent(fn (): View => view('filament.components.file-viewer', [
                'files' => $this->fileList(),
                'label' => (string) $this->getLabel(),
            ]));
    }

    /**
     * Metadata tiap berkas untuk ditampilkan pada modal.
     *
     * @return array<int, array{url: ?string, extension: string, name: string}>
     */
    protected function fileList(): array
    {
        return array_map(fn (string $path): array => [
            'url' => $this->temporaryUrl($path),
            'extension' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            'name' => basename($path),
        ], $this->getPaths());
    }

    /**
     * URL sementara berumur pendek untuk berkas privat.
     */
    protected function temporaryUrl(string $path): ?string
    {
        return Storage::disk(config('filament.default_filesystem_disk'))
            ->temporaryUrl($path, now()->addMinutes(30));
    }
}
