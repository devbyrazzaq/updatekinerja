<?php

namespace App\Imports;

use App\Exports\ProgramsExport;
use App\Models\Program;

class ProgramsImport extends Import
{
    /**
     * Label kolom mengikuti kelas ekspor pasangannya, supaya berkas hasil ekspor
     * bisa langsung disunting lalu diimpor kembali.
     */
    public function columnLabels(): array
    {
        return (new ProgramsExport)->columnLabels();
    }

    public function headings(): array
    {
        return ['name'];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function templateColumns(): array
    {
        return ['name', 'description', 'is_active'];
    }

    public function sampleRows(): array
    {
        return [
            ['Tridharma Perguruan Tinggi', 'Program tridharma', 1],
            ['Tata Kelola', 'Program tata kelola', 1],
        ];
    }

    public function templateFilename(): string
    {
        return 'template-impor-program';
    }

    public function storeRow(array $row): void
    {
        Program::updateOrCreate(
            ['name' => $row['name']],
            [
                'description' => $row['description'] ?? null,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ],
        );
    }
}
