<?php

namespace App\Filament\Actions;

use Filament\Actions\CreateAction;

class AuthorizedCreateAction extends CreateAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->visible(fn (): bool => $this->canCreateRecord());
    }

    protected function canCreateRecord(): bool
    {
        $livewire = $this->getLivewire();

        if (! is_object($livewire) || ! method_exists($livewire, 'getResource')) {
            return false;
        }

        $resource = $livewire->getResource();

        if (! is_string($resource) || ! method_exists($resource, 'canCreate')) {
            return false;
        }

        return (bool) $resource::canCreate();
    }
}
