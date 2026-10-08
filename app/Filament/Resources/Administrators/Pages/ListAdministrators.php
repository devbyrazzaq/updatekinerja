<?php

namespace App\Filament\Resources\Administrators\Pages;

use App\Filament\Resources\Administrators\AdministratorResource;
use App\Filament\Resources\Users\Pages\ListUsers;

class ListAdministrators extends ListUsers
{
    protected static string $resource = AdministratorResource::class;
}
