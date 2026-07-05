<?php

namespace App\Filament\Actions;

use Closure;
use Filament\Actions\Action;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class AuthorizedAction extends Action
{
    protected string|Closure|null $permissionName = null;

    public function permission(string|Closure $permission): static
    {
        $this->permissionName = $permission;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->visible(fn (): bool => $this->isVisibleForCurrentUser());
    }

    protected function isVisibleForCurrentUser(): bool
    {
        $user = auth()->user();

        if (! $user instanceof Authenticatable) {
            return false;
        }

        if ($user?->can('bypass_data_scope') ?? false) {
            return true;
        }

        if ($this->permissionName instanceof Closure) {
            return (bool) $this->evaluate($this->permissionName);
        }

        $permissionName = $this->evaluate($this->permissionName);

        if (! is_string($permissionName) || blank($permissionName)) {
            return false;
        }

        if (! method_exists($user, 'hasPermissionTo')) {
            return false;
        }

        try {
            return $user->hasPermissionTo($permissionName);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
