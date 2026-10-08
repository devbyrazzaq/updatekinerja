<?php

namespace App\Filament\Resources\Administrators\Pages;

use App\Filament\Resources\Administrators\AdministratorResource;
use App\Filament\Resources\Users\Pages\CreateUser;

class CreateAdministrator extends CreateUser
{
    protected static string $resource = AdministratorResource::class;
}
