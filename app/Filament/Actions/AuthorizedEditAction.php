<?php

namespace App\Filament\Actions;

use Filament\Actions\EditAction;
use Illuminate\Database\Eloquent\Model;

class AuthorizedEditAction extends EditAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->visible(fn (): bool => $this->canEditRecord());
    }

    protected function canEditRecord(): bool
    {
        $livewire = $this->getLivewire();
        $record = $this->getRecord();

        if (! is_object($livewire) || ! method_exists($livewire, 'getResource') || ! $record instanceof Model) {
            return false;
        }

        $resource = $livewire->getResource();

        if (! is_string($resource) || ! method_exists($resource, 'canEdit')) {
            return false;
        }

        return (bool) $resource::canEdit($record);
    }
}
