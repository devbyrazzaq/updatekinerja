<?php

namespace App\Filament\Actions;

use App\Imports\Import;
use App\Imports\ImportResult;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Arrayable;
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

    /**
     * Konteks tetap yang tidak diisikan pengguna, mis. cakupan tahun kerja dan unit
     * kerja yang sedang dibaca halaman pemanggilnya. Nilainya dipakai baik saat
     * mengunduh template maupun saat berkasnya diimpor, sehingga daftar referensi di
     * berkas template selalu sama dengan yang diterima saat impor.
     *
     * @var array<string, mixed>|Closure
     */
    protected array|Closure $importerContext = [];

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

    /**
     * Konteks tetap dari halaman pemanggil (lihat {@see $importerContext}).
     *
     * @param  array<string, mixed>|Closure  $context
     */
    public function importerContext(array|Closure $context): static
    {
        $this->importerContext = $context;

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
                        // Isian form modal ikut dibawa, sehingga pilihan yang mengubah
                        // bentuk template — mis. sumber lembar referensi — sudah berlaku
                        // pada berkas yang diunduh. Field yang menentukannya perlu
                        // `->live()` agar nilainya sudah tersimpan saat tombol ditekan.
                        ->action(fn (Component $component) => app($this->importer)
                            ->withContext([...$this->getImporterContext(), ...$this->getFormState($component)])
                            ->downloadTemplate()),
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
     * Isian form modal impor ini, tanpa berkas unggahannya. Dibaca dari container milik
     * field berkas — bukan dari aksi yang sedang di-mount — karena tombol "Unduh
     * Template" sendiri adalah aksi bersarang, sehingga aksi terakhir yang di-mount
     * justru tombol itu dan datanya kosong.
     *
     * @return array<string, mixed>
     */
    protected function getFormState(Component $component): array
    {
        $state = $component->getContainer()->getRawState();

        return Arr::except($state instanceof Arrayable ? $state->toArray() : $state, 'file');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getImporterContext(): array
    {
        return $this->evaluate($this->importerContext) ?? [];
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
                // Konteks dari form modal boleh menimpa konteks tetap halaman, bukan
                // sebaliknya, supaya field tambahan tetap punya kata akhir.
                ->withContext([...$this->getImporterContext(), ...$context])
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
        // Catatan tidak membatalkan apa pun, tetapi harus terbaca: barisnya tersimpan
        // justru dengan keadaan yang perlu diketahui pengimpor.
        if ($result->hasWarnings()) {
            Notification::make()
                ->title('Impor selesai dengan catatan')
                ->body("{$result->imported} baris berhasil diimpor. ".implode(' ', $result->warnings))
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('Impor selesai')
            ->body("{$result->imported} baris berhasil diimpor.")
            ->success()
            ->send();
    }
}
