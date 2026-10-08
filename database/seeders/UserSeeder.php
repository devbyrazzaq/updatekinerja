<?php

namespace Database\Seeders;

use App\Enums\EnumJenisKelamin;
use App\Enums\EnumRole;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::updateOrCreate(
            ['email' => 'razzaqfattahur@gmail.com'],
            [
                'name' => 'Moch Fattahur Razzaq',
                'username' => 'superadmin',
                'password' => Hash::make('password'),
                'gender' => EnumJenisKelamin::LakiLaki,
                'birth_date' => '2000-01-01',
                'is_active' => true,
            ],
        );
        $superAdmin->syncRoles([EnumRole::SuperAdmin->value]);

        $unitTeknik = UnitKerja::where('slug', 'fakultas-sains-teknologi-dan-pendidikan-fstp')->first();
        $unitKesehatan = UnitKerja::where('slug', 'fakultas-ilmu-kesehatan-fik')->first();

        $accounts = [
            ['Admin Sistem', 'admin', EnumRole::Admin, null],
            ['Rektor UMLA', 'rektor', EnumRole::Rektor, null],
            ['Wakil Rektor II', 'wakilrektor', EnumRole::WakilRektorII, null],
            ['Staf Biro Keuangan', 'keuangan', EnumRole::BiroKeuangan, null],
            ['Verifikator Laporan', 'verifikator', EnumRole::VerifikatorLaporan, null],
            ['Kepala Fakultas Sains, Teknologi dan Pendidikan', 'unitteknik', EnumRole::UnitKerja, $unitTeknik?->id],
        ];

        foreach ($accounts as [$name, $username, $role, $unitKerjaId]) {
            $user = User::updateOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'email' => $username.'@umla.ac.id',
                    'password' => Hash::make($username.'01'),
                    'gender' => EnumJenisKelamin::LakiLaki,
                    'birth_date' => '2000-01-01',
                    'unit_kerja_id' => $unitKerjaId,
                    'is_active' => true,
                ],
            );
            $user->syncRoles([$role->value]);
        }

        $this->seedPimpinanUnit($unitTeknik, $unitKesehatan);
    }

    /**
     * Contoh Pimpinan Unit yang membawahi dua unit kerja sekaligus: unit utamanya
     * ditandai lewat `unit_kerja_id`, unit tambahan diberikan lewat role pembatas
     * data per unit sehingga bisa dipindah lewat pengalih unit di topbar.
     */
    private function seedPimpinanUnit(?UnitKerja $unitUtama, ?UnitKerja $unitTambahan): void
    {
        if ($unitUtama === null) {
            return;
        }

        $pimpinan = User::updateOrCreate(
            ['username' => 'pimpinanunit'],
            [
                'name' => 'Pimpinan Unit Contoh',
                'email' => 'pimpinanunit@umla.ac.id',
                'password' => Hash::make('pimpinanunit01'),
                'gender' => EnumJenisKelamin::LakiLaki,
                'birth_date' => '2000-01-01',
                'unit_kerja_id' => $unitUtama->id,
                'is_active' => true,
            ],
        );

        $roles = [EnumRole::PimpinanUnit->value, EnumRole::unitScopeName($unitUtama->name)];

        if ($unitTambahan !== null) {
            $roles[] = EnumRole::unitScopeName($unitTambahan->name);
        }

        $pimpinan->syncRoles($roles);
    }
}
