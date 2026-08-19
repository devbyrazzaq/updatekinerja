<?php

namespace Database\Factories;

use App\Enums\EnumJenisWaktuPemasukan;
use App\Enums\EnumStatusPemasukan;
use App\Enums\EnumSumberPemasukan;
use App\Models\Pemasukan;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pemasukan>
 */
class PemasukanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_kerja_id' => UnitKerja::factory(),
            'user_id' => User::factory(),
            // Sengaja tanpa tautan pengajuan/realisasi: pemasukan boleh berdiri sendiri,
            // dan Buku Anggaran memang menangani kasus itu lewat tanggal pelaksanaan.
            'sumber' => EnumSumberPemasukan::Realisasi,
            'rincian_kegiatan' => fake()->sentence(3),
            'jenis_waktu' => EnumJenisWaktuPemasukan::SatuHari,
            'tanggal_pelaksanaan' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'nominal_pendapatan' => fake()->numberBetween(1, 20) * 500_000,
            'status' => EnumStatusPemasukan::Draft,
        ];
    }

    /**
     * Pemasukan yang berlangsung pada rentang tanggal, bukan sehari saja.
     */
    public function rentang(?string $mulai = null, ?string $selesai = null): static
    {
        return $this->state(fn (): array => [
            'jenis_waktu' => EnumJenisWaktuPemasukan::Rentang,
            'tanggal_pelaksanaan' => $mulai ?? now()->subDays(3)->format('Y-m-d'),
            'tanggal_selesai' => $selesai ?? now()->format('Y-m-d'),
        ]);
    }

    /**
     * Riwayat maju yang seharusnya sudah tercatat saat pemasukan berada pada sebuah
     * status. Model membaca riwayat ini untuk menentukan posisi stepper dan tujuan
     * pengajuan ulang, sehingga record uji tanpa log akan berperilaku seolah baru saja
     * diajukan dari awal.
     *
     * @return array<int, EnumStatusPemasukan>
     */
    protected static function riwayatHingga(EnumStatusPemasukan $status): array
    {
        $alur = [
            EnumStatusPemasukan::Diajukan,
            EnumStatusPemasukan::VerifikasiKeuangan,
            EnumStatusPemasukan::MenungguBukti,
            EnumStatusPemasukan::Valid,
        ];

        $batas = match ($status) {
            EnumStatusPemasukan::Diajukan, EnumStatusPemasukan::VerifikasiWakil => EnumStatusPemasukan::Diajukan,
            EnumStatusPemasukan::VerifikasiKeuangan,
            EnumStatusPemasukan::MenungguBukti,
            EnumStatusPemasukan::Valid => $status,
            default => null,
        };

        if ($batas === null) {
            return [];
        }

        return array_slice($alur, 0, array_search($batas, $alur, true) + 1);
    }

    /**
     * Pemasukan pada status tertentu, lengkap dengan kolom aktor, timestamp, dan
     * riwayat tahap yang sudah dilewati — supaya scope antrean tiap Resource verifikasi
     * (yang membaca kolom aktor) dan pembacaan riwayat oleh model ikut konsisten.
     */
    public function berstatus(EnumStatusPemasukan $status, ?User $aktor = null): static
    {
        return $this->afterCreating(function (Pemasukan $pemasukan) use ($status, $aktor): void {
            foreach (static::riwayatHingga($status) as $tahap) {
                $pemasukan->catatLog($tahap, $aktor?->getKey() ?? $pemasukan->user_id);
            }
        })->state(function () use ($status, $aktor): array {
            $aktorId = $aktor?->getKey();
            $tahapDilewati = match ($status) {
                EnumStatusPemasukan::VerifikasiKeuangan => ['wakil'],
                EnumStatusPemasukan::MenungguBukti, EnumStatusPemasukan::Valid => ['wakil', 'keuangan'],
                default => [],
            };

            $atribut = ['status' => $status];

            foreach ($tahapDilewati as $tahap) {
                $atribut["{$tahap}_id"] = $aktorId ?? User::factory();
                $atribut["disetujui_{$tahap}_at"] = now();
            }

            if ($status === EnumStatusPemasukan::Valid) {
                $atribut['bukti_path'] = ['bukti-pemasukan/contoh-bukti.pdf'];
                $atribut['bukti_original_names'] = ['contoh-bukti.pdf'];
                $atribut['bukti_diserahkan_at'] = now();
                $atribut['divalidasi_at'] = now();
            }

            return $atribut;
        });
    }

    /**
     * Pemasukan yang sudah sah, satu-satunya status yang dihitung Buku Anggaran.
     */
    public function valid(): static
    {
        return $this->berstatus(EnumStatusPemasukan::Valid);
    }
}
