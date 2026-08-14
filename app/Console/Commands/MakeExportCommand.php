<?php

namespace App\Console\Commands;

class MakeExportCommand extends AbstractSpreadsheetMakeCommand
{
    protected $name = 'make:export';

    protected $description = 'Buat kelas Export spreadsheet baru';

    protected $type = 'Export';

    protected function getStub(): string
    {
        return base_path('stubs/export.stub');
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Exports';
    }

    protected function classSuffix(): string
    {
        return 'Export';
    }
}
