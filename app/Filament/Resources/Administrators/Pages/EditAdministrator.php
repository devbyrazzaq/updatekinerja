<?php

namespace App\Filament\Resources\Administrators\Pages;

use App\Filament\Resources\Administrators\AdministratorResource;
use App\Filament\Resources\Users\Pages\EditUser;

class EditAdministrator extends EditUser
{
    protected static string $resource = AdministratorResource::class;
}
