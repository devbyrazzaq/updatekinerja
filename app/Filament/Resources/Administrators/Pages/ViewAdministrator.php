<?php

namespace App\Filament\Resources\Administrators\Pages;

use App\Filament\Resources\Administrators\AdministratorResource;
use App\Filament\Resources\Users\Pages\ViewUser;

class ViewAdministrator extends ViewUser
{
    protected static string $resource = AdministratorResource::class;
}
