<?php

namespace App\Filament\Actions;

use App\Imports\Import;
use App\Imports\ImportResult;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Tombol impor data dari berkas .xlsx/.csv. Sambungkan sebuah {@see Import}
 * lewat {@see importer()} dan permission lewat {@see permission()}.
 */
class ExcelImportAction extends AuthorizedAction
{
    /**
     * @var class-string<Import>
     */
    protected string $importer;

    /**
     * Field form tambahan yang ditampilkan di atas unggahan berkas. Nilainya
     * diteruskan ke Import sebagai konteks (lihat {@see Import::context()}).
     *
     * @var array<int, mixed>|Closure
     */
    protected array|Closure $formFields = [];

    public static function getDefaultName(): ?string
    {
        return 'import';
    }

    /**
     * @param  class-string<Import>  $importer
     */
    public function importer(string $importer): static
    {
        $this->importer = $importer;

        return $this;
    }

    /**
     * Tambahkan field form yang muncul di atas unggahan berkas. Nilai yang
     * disubmit tersedia di Import lewat {@see Import::context()}.
     *
     * @param  array<int, mixed>|Closure  $components
     */
    public function formFields(array|Closure $components): static
    {
        $this->formFields = $components;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Impor');
        $this->icon(Heroicon::ArrowUpTray);
        $this->color('gray');
        $this->modalHeading('Impor Data');
        $this->modalDescription('Unduh template terlebih dahulu, isi mengikuti contoh, lalu unggah kembali berkasnya.');
        $this->modalSubmitActionLabel('Impor');

        $this->schema(fn (): array => [
            ...$this->getFormFields(),
            FileUpload::make('file')
                ->label('Berkas')
                ->hintAction(
                    Action::make('downloadTemplate')
                        ->label('Unduh Template')
                        ->icon(Heroicon::ArrowDownTray)
                        ->color('primary')
                        ->action(fn () => app($this->importer)->downloadTemplate()),
                )
                ->helperText('Format .xlsx atau .csv. Baris pertama harus berupa nama kolom.')
                ->acceptedFileTypes([
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'text/csv',
                    'application/csv',
                    'text/plain',
                ])
                ->storeFiles(false)
                ->required(),
        ]);

        $this->action(function (array $data, $livewire): void {
            $file = is_array($data['file']) ? Arr::first($data['file']) : $data['file'];

            if (! $file instanceof TemporaryUploadedFile) {
                return;
            }

            $result = $this->runImport($file, Arr::except($data, 'file'));

            // Validasi gagal: batalkan impor dan tampilkan pesan di bawah field berkas.
            if ($result->failed()) {
                $this->failFileField($livewire, $result->errors);
            }

            $this->notifyResult($result);
        });
    }

    /**
     * @return array<int, mixed>
     */
    protected function getFormFields(): array
    {
        return $this->evaluate($this->formFields) ?? [];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function runImport(TemporaryUploadedFile $file, array $context): ImportResult
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $path = tempnam(sys_get_temp_dir(), 'import_').'.'.$extension;
        copy($file->getRealPath(), $path);

        try {
            return app($this->importer)
                ->withContext($context)
                ->import($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * Lampirkan pesan error ke field berkas milik action yang sedang di-mount,
     * sehingga muncul tepat di bawahnya dan modal tetap terbuka.
     *
     * @param  list<string>  $errors
     */
    protected function failFileField(object $livewire, array $errors): never
    {
        $index = array_key_last($livewire->mountedActions ?? []);

        throw ValidationException::withMessages([
            "mountedActions.{$index}.data.file" => $errors,
        ]);
    }

    protected function notifyResult(ImportResult $result): void
    {
        Notification::make()
            ->title('Impor selesai')
            ->body("{$result->imported} baris berhasil diimpor.")
            ->success()
            ->send();
    }
}
