<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Proposal & laporan realisasi kini dapat berisi lebih dari satu berkas, sehingga
 * kolomnya diubah menjadi teks penampung array JSON. Nilai lama (path tunggal)
 * dibungkus menjadi array satu elemen agar tetap terbaca oleh cast array.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('realisasi_program_kerjas', function (Blueprint $table): void {
            $table->text('proposal_path')->nullable()->change();
            $table->text('laporan_path')->nullable()->change();
        });

        foreach (['proposal_path', 'laporan_path'] as $column) {
            DB::table('realisasi_program_kerjas')
                ->whereNotNull($column)
                ->orderBy('id')
                ->each(function (object $row) use ($column): void {
                    $nilai = $row->{$column};

                    if (! is_string($nilai) || $nilai === '' || str_starts_with(ltrim($nilai), '[')) {
                        return;
                    }

                    DB::table('realisasi_program_kerjas')
                        ->where('id', $row->id)
                        ->update([$column => json_encode([$nilai])]);
                });
        }
    }

    public function down(): void
    {
        foreach (['proposal_path', 'laporan_path'] as $column) {
            DB::table('realisasi_program_kerjas')
                ->whereNotNull($column)
                ->orderBy('id')
                ->each(function (object $row) use ($column): void {
                    $nilai = json_decode((string) $row->{$column}, true);

                    if (! is_array($nilai)) {
                        return;
                    }

                    DB::table('realisasi_program_kerjas')
                        ->where('id', $row->id)
                        ->update([$column => $nilai[0] ?? null]);
                });
        }

        Schema::table('realisasi_program_kerjas', function (Blueprint $table): void {
            $table->string('proposal_path')->nullable()->change();
            $table->string('laporan_path')->nullable()->change();
        });
    }
};
