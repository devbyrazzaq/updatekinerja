<?php

namespace App\Filament\Actions;

use App\Contracts\RestrictsDeletion;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class CaptchaDeleteAction extends DeleteAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Hapus');
        $this->visible(fn (): bool => $this->canDeleteRecord());
        $this->schema(fn (): array => $this->getCaptchaSchema());
        $this->before(function (array $data): void {
            $record = $this->getRecord();

            if ($record instanceof RestrictsDeletion && ($reason = $record->getDeletionRestrictionReason()) !== null) {
                Notification::make()
                    ->title('Tidak dapat dihapus')
                    ->body($reason)
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
        $this->modalHeading('Hapus Data');
        $this->modalDescription('Data yang dihapus tidak dapat dikembalikan.');
        $this->modalSubmitActionLabel('Ya, Hapus');
        $this->successNotificationTitle('Data berhasil dihapus.');
    }

    protected function canDeleteRecord(): bool
    {
        $livewire = $this->getLivewire();
        $record = $this->getRecord();

        if (! is_object($livewire) || ! method_exists($livewire, 'getResource') || ! $record instanceof Model) {
            return false;
        }

        $resource = $livewire->getResource();

        if (! is_string($resource) || ! method_exists($resource, 'canDelete')) {
            return false;
        }

        return (bool) $resource::canDelete($record);
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
