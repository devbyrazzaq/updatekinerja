<?php

namespace App\Filament\Actions;

use App\Contracts\RestrictsDeletion;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class CaptchaDeleteBulkAction extends DeleteBulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Hapus Massal');
        $this->visible(fn (): bool => $this->canDeleteAnyRecords());
        $this->accessSelectedRecords();
        $this->schema(fn (): array => $this->getCaptchaSchema());
        $this->before(function (array $data): void {
            $blocked = $this->getSelectedRecords()
                ->filter(fn (Model $record): bool => $record instanceof RestrictsDeletion
                    && $record->getDeletionRestrictionReason() !== null);

            if ($blocked->isNotEmpty()) {
                Notification::make()
                    ->title('Sebagian data tidak dapat dihapus')
                    ->body("{$blocked->count()} data terpilih masih digunakan oleh data lain sehingga tidak dapat dihapus. Batalkan pilihan data tersebut terlebih dahulu.")
                    ->danger()
                    ->send();

                $this->halt();
            }

            if ((int) ($data['captcha_answer'] ?? 0) === (int) ($data['captcha_count'] ?? 0)) {
                return;
            }

            Notification::make()
                ->title('Gagal!')
                ->body('Jawaban salah. Silahkan coba lagi.')
                ->danger()
                ->send();

            $this->halt();
        });
        $this->modalHeading('Hapus Data Terpilih');
        $this->modalDescription('Data yang dihapus tidak dapat dikembalikan.');
        $this->modalSubmitActionLabel('Ya, Hapus Semua');
        $this->successNotificationTitle(fn (): string => $this->successfulSelectedRecordsCount > 0
            ? "Penghapusan massal berhasil. {$this->successfulSelectedRecordsCount} data berhasil dihapus."
            : 'Penghapusan massal berhasil.');
    }

    protected function canDeleteAnyRecords(): bool
    {
        $livewire = $this->getLivewire();

        if (! is_object($livewire) || ! method_exists($livewire, 'getResource')) {
            return false;
        }

        $resource = $livewire->getResource();

        if (! is_string($resource) || ! method_exists($resource, 'canDeleteAny')) {
            return false;
        }

        return (bool) $resource::canDeleteAny();
    }

    /**
     * @return array<int, Hidden|TextInput>
     */
    protected function getCaptchaSchema(): array
    {
        $captcha = $this->generateCaptcha();

        return [
            Hidden::make('captcha_count')
                ->default($captcha['answer'])
                ->dehydrated(),
            TextInput::make('captcha_answer')
                ->label('Konfirmasi Penghapusan')
                ->required()
                ->numeric()
                ->prefix($captcha['text'])
                ->helperText('Ketik hasil operasi untuk konfirmasi penghapusan'),
        ];
    }

    /**
     * @return array{answer: int, text: string}
     */
    protected function generateCaptcha(): array
    {
        $firstNumber = random_int(1, 20);
        $secondNumber = random_int(1, 20);
        $operator = ['+', '-'][random_int(0, 1)];
        $answer = $operator === '+'
            ? $firstNumber + $secondNumber
            : $firstNumber - $secondNumber;

        return [
            'answer' => $answer,
            'text' => "{$firstNumber} {$operator} {$secondNumber} = ...",
        ];
    }
}
