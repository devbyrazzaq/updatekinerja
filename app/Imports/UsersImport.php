<?php

namespace App\Imports;

use App\Exports\UsersExport;
use App\Models\UnitKerja;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class UsersImport extends Import
{
    /**
     * Label kolom mengikuti kelas ekspor pasangannya, supaya berkas hasil ekspor
     * bisa langsung disunting lalu diimpor kembali.
     */
    public function columnLabels(): array
    {
        return (new UsersExport)->columnLabels();
    }

    public function headings(): array
    {
        return ['name', 'username'];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'front_title' => ['nullable', 'string', 'max:255'],
            'back_title' => ['nullable', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'in:L,P'],
            'birth_date' => ['nullable', 'date'],
            'unit_kerja' => ['nullable', 'string'],
            'roles' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function templateColumns(): array
    {
        return ['name', 'front_title', 'back_title', 'username', 'email', 'phone', 'gender', 'birth_date', 'unit_kerja', 'roles', 'is_active'];
    }

    public function sampleRows(): array
    {
        return [
            ['Budi Santoso', 'Dr.', 'S.Kom.', 'budi', 'budi@example.com', '081234567890', 'L', '1990-04-15', 'Fakultas Teknik', 'Unit Kerja', 1],
        ];
    }

    public function templateFilename(): string
    {
        return 'template-impor-pengguna';
    }

    public function storeRow(array $row): void
    {
        $unitKerjaId = ! empty($row['unit_kerja'])
            ? UnitKerja::where('name', $row['unit_kerja'])->value('id')
            : null;

        $birthDate = ! empty($row['birth_date']) ? Carbon::parse($row['birth_date']) : null;

        $user = User::updateOrCreate(
            ['username' => $row['username']],
            [
                'name' => $row['name'],
                'front_title' => $row['front_title'] ?? null,
                'back_title' => $row['back_title'] ?? null,
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?? null,
                'gender' => $row['gender'] ?? null,
                'birth_date' => $birthDate,
                'unit_kerja_id' => $unitKerjaId,
                'is_active' => (bool) ($row['is_active'] ?? true),
                'password' => Hash::make($row['username'].($birthDate ?? now())->format('d')),
            ],
        );

        if (! empty($row['roles'])) {
            $roles = collect(explode(',', (string) $row['roles']))
                ->map(fn (string $role): string => trim($role))
                ->filter()
                ->all();

            $user->syncRoles($roles);
        }
    }
}
