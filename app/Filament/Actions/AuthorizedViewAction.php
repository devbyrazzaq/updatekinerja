<?php

namespace App\Filament\Actions;

use Filament\Actions\ViewAction;
use Illuminate\Database\Eloquent\Model;

class AuthorizedViewAction extends ViewAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->visible(fn (): bool => $this->canViewRecord());
    }

    protected function canViewRecord(): bool
    {
        $livewire = $this->getLivewire();
        $record = $this->getRecord();

        if (! is_object($livewire) || ! method_exists($livewire, 'getResource') || ! $record instanceof Model) {
            return false;
        }

        $resource = $livewire->getResource();

        if (! is_string($resource) || ! method_exists($resource, 'canView')) {
            return false;
        }

        return (bool) $resource::canView($record);
    }
}
