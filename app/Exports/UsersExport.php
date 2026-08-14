<?php

namespace App\Exports;

use App\Models\User;

class UsersExport extends Export
{
    public function filename(): string
    {
        return 'pengguna-'.now()->format('Y-m-d');
    }

    public function title(): string
    {
        return 'Data Pengguna';
    }

    public function subtitle(): ?string
    {
        return 'Daftar akun pengguna sistem beserta unit kerja dan peran yang dimiliki.';
    }

    public function headings(): array
    {
        return ['name', 'front_title', 'back_title', 'username', 'email', 'phone', 'gender', 'birth_date', 'unit_kerja', 'roles', 'is_active'];
    }

    public function columnLabels(): array
    {
        return [
            'name' => 'Nama Lengkap',
            'front_title' => 'Gelar Depan',
            'back_title' => 'Gelar Belakang',
            'username' => 'Nama Pengguna',
            'email' => 'Surel',
            'phone' => 'Telepon',
            'gender' => 'Jenis Kelamin',
            'birth_date' => 'Tanggal Lahir',
            'unit_kerja' => 'Unit Kerja',
            'roles' => 'Peran',
            'is_active' => 'Status',
        ];
    }

    public function summary(): array
    {
        $query = User::query();

        return [
            'Total Pengguna' => number_format((clone $query)->count(), 0, ',', '.'),
            'Aktif' => number_format((clone $query)->where('is_active', true)->count(), 0, ',', '.'),
            'Unit Kerja Tercakup' => number_format($query->distinct()->count('unit_kerja_id'), 0, ',', '.'),
        ];
    }

    public function rows(): iterable
    {
        return User::query()
            ->with(['unitKerja', 'roles'])
            ->orderBy('name')
            ->lazy()
            ->map(fn (User $user): array => [
                $user->name,
                $user->front_title,
                $user->back_title,
                $user->username,
                $user->email,
                $user->phone,
                $user->gender?->value,
                $user->birth_date?->format('Y-m-d'),
                $user->unitKerja?->name,
                $user->roles->pluck('name')->implode(', '),
                (int) $user->is_active,
            ]);
    }
}
