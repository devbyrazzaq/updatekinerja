<?php

namespace App\Console\Commands;

class MakeImportCommand extends AbstractSpreadsheetMakeCommand
{
    protected $name = 'make:import';

    protected $description = 'Buat kelas Import spreadsheet baru';

    protected $type = 'Import';

    protected function getStub(): string
    {
        return base_path('stubs/import.stub');
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Imports';
    }

    protected function classSuffix(): string
    {
        return 'Import';
    }
}
